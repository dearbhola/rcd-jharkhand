<?php

namespace App\Domain\Reporting;

use App\Domain\Audit\AuditLogger;
use App\Domain\Evidence\EvidenceRules;
use App\Domain\Evidence\EvidenceService;
use App\Domain\Gis\LocationMatch;
use App\Domain\Responsibility\ResponsibilityResolver;
use App\Domain\Workflow\TransitionInput;
use App\Domain\Workflow\WorkflowEngine;
use App\Domain\Workflow\WorkflowStarter;
use App\Events\ReportSubmitted;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\IssueCategory;
use App\Models\Report;
use App\Models\ReportResponsibility;
use App\Models\Severity;
use App\Models\User;
use App\Support\NumberSequence;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Accepts a field report: idempotent on client_uuid, evidence validated before any write,
 * location and responsibility resolved on the server, everything in one transaction.
 */
class ReportService
{
    public function __construct(
        private readonly LocationValidator $location,
        private readonly ResponsibilityResolver $resolver,
        private readonly EvidenceService $evidence,
        private readonly DuplicateDetector $duplicates,
        private readonly NumberSequence $sequence,
        private readonly AuditLogger $audit,
        private readonly WorkflowStarter $workflow,
        private readonly WorkflowEngine $engine,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return array{report: Report, created: bool}
     */
    public function submit(User $reporter, ReportSubmission $s, array $files): array
    {
        if ($existing = $this->existing($reporter, $s->clientUuid)) {
            return ['report' => $existing, 'created' => false];
        }

        $checked = $this->evidence->validateBatch(EvidenceRules::CONTEXT_REPORT, $files);
        ['match' => $match, 'flags' => $flags, 'override' => $override] = $this->location->validateReport(
            $reporter, $s->latitude, $s->longitude, $s->accuracyM, $s->capturedAt, $s->locationOverride,
        );

        [$assetType, $asset] = $this->target($s, $match);
        $this->assertCategory($s, $assetType);

        $resolution = $this->resolver->resolve($match);
        if ($resolution->gaps()) {
            $flags[] = 'responsibility_gap';
        }

        $report = DB::transaction(function () use ($reporter, $s, $checked, $match, $flags, $override, $assetType, $asset, $resolution) {
            $now = now();
            $fy = NumberSequence::financialYear($now);
            $prefix = config('rcd.report_no_prefix');
            $isTest = $match->road->is_test || $override;

            $report = Report::create([
                'report_no' => sprintf('%s/%s/%06d', $prefix, $fy, $this->sequence->next("report:{$fy}")),
                'client_uuid' => $s->clientUuid,
                'source' => $s->source,
                'reporter_id' => $reporter->id,
                'reporter_role_code' => $reporter->primaryRoleCode()?->value ?? 'UNKNOWN',
                'road_id' => $match->road->id,
                'road_section_id' => $match->section->id,
                'asset_id' => $asset?->id,
                'asset_type_id' => $assetType->id,
                'chainage_m' => $match->chainageM,
                'distance_from_road_m' => round($match->distanceM, 2),
                'division_id' => $match->section->division_id,
                'latitude' => $s->latitude,
                'longitude' => $s->longitude,
                'gps_accuracy_m' => $s->accuracyM,
                'captured_at_device' => $s->capturedAt,
                'gps_fix_at' => $s->gpsFixAt,
                'received_at' => $now,
                'location_flags' => $flags ?: null,
                'location_override' => $override,
                'issue_category_id' => $s->issueCategoryId,
                'severity_id' => $s->severityId,
                'description' => $s->description,
                'status' => ReportStatus::PENDING_VALIDATION,
                'is_test' => $isTest,
            ]);

            ReportResponsibility::create([
                'report_id' => $report->id,
                'contract_id' => $resolution->contract?->id,
                'contract_road_section_id' => $resolution->mapping?->id,
                'contractor_id' => $resolution->contractor?->id,
                'maintenance_active' => $resolution->maintenanceActive,
                'je_user_id' => $resolution->je?->id,
                'ae_user_id' => $resolution->ae?->id,
                'ee_user_id' => $resolution->ee?->id,
                'route' => $resolution->route(),
                'reason' => 'initial',
                'is_current' => true,
                'resolved_at' => $now,
            ]);

            foreach ($checked as $item) {
                $this->evidence->store($item, $report, $report, $reporter, [
                    'lat' => $s->latitude, 'lng' => $s->longitude, 'accuracy' => $s->accuracyM, 'captured_at' => $s->capturedAt,
                ]);
            }

            $duplicates = $this->duplicates->near($s->latitude, $s->longitude, $s->issueCategoryId, $report->id);
            if ($duplicates->isNotEmpty()) {
                $report->update(['location_flags' => array_values(array_unique([...($report->location_flags ?? []), 'possible_duplicate']))]);
            }

            $report->update(['finalized_at' => now()]);

            // Start the workflow in the same transaction: a report never exists without one.
            $this->workflow->start($report);
            $role = $report->reporter_role_code;
            if (in_array($role, $this->settings->get('workflow.auto_validate_roles', []), true)) {
                $this->engine->transition($report, 'validate', null, TransitionInput::system("Auto-validated: reported by {$role}"));
            }

            $this->audit->log('report.created', $report, null, [
                'report_no' => $report->report_no, 'road' => $match->road->code, 'section' => $match->section->code,
                'chainage_m' => $match->chainageM, 'route' => $resolution->route(), 'evidence' => count($checked),
                'flags' => $report->location_flags, 'override' => $override,
            ], actor: $reporter);

            return $report;
        });

        ReportSubmitted::dispatch($report);

        return ['report' => $report, 'created' => true];
    }

    private function existing(User $reporter, string $clientUuid): ?Report
    {
        $existing = Report::withoutGlobalScopes()->where('client_uuid', $clientUuid)->first();
        if ($existing && $existing->reporter_id !== $reporter->id) {
            throw ValidationException::withMessages(['client_uuid' => 'This submission identifier is already in use.']);
        }

        return $existing;
    }

    /** @return array{0: AssetType, 1: ?Asset} */
    private function target(ReportSubmission $s, LocationMatch $match): array
    {
        $type = AssetType::where('is_active', true)->find($s->assetTypeId)
            ?? throw ValidationException::withMessages(['asset_type_id' => 'Choose what is damaged.']);

        if ($type->code === AssetType::ROAD) {
            return [$type, null];
        }
        if ($match->asset && $match->asset->asset_type_id === $type->id) {
            return [$type, $match->asset];
        }

        throw ValidationException::withMessages(['asset_type_id' => "No {$type->name} is mapped at your location. Report it as road damage or move closer to the structure."]);
    }

    private function assertCategory(ReportSubmission $s, AssetType $type): void
    {
        $ok = IssueCategory::where('id', $s->issueCategoryId)->where('asset_type_id', $type->id)->where('is_active', true)->exists();
        if (! $ok) {
            throw ValidationException::withMessages(['issue_category_id' => 'Choose a damage category for '.$type->name.'.']);
        }
        if (! Severity::where('id', $s->severityId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['severity_id' => 'Choose a severity.']);
        }
    }
}
