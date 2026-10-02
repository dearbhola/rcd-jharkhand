<?php

namespace App\Domain\Workflow;

use App\Domain\Evidence\EvidenceRules;
use App\Domain\Evidence\EvidenceService;
use App\Domain\Reporting\LocationValidator;
use App\Domain\Sla\SlaService;
use App\Domain\Workflow\Effects as E;
use App\Domain\Workflow\Guards as G;
use App\Support\Settings;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Maps the guard/effect keys stored in workflow_transitions to implementations.
 * Administrators rewire steps and transitions in data; new behaviour needs a new key here.
 */
class Registry
{
    public function __construct(private readonly Container $app) {}

    public function guard(string $key): Guard
    {
        $radius = fn (string $setting, bool $onlyIfConfigured = false) => new G\WithinSiteRadius(
            $this->app->make(Settings::class), $this->app->make(LocationValidator::class), $setting, $onlyIfConfigured);
        $evidence = fn (string $context) => new G\EvidenceRequirements($this->app->make(EvidenceService::class), $context);

        return match ($key) {
            'assignee.is_actor' => new G\AssigneeIsActor,
            'actor.mapped_engineer' => new G\ActorIsMappedEngineer,
            'location.within_repair_radius' => $radius('repair.location_radius_m'),
            'location.within_review_radius' => $radius('review.location_radius_m'),
            'location.validation_if_required' => $radius('review.location_radius_m', true),
            'evidence.repair_requirements' => $evidence(EvidenceRules::CONTEXT_REPAIR),
            'evidence.inspection_requirements' => $evidence(EvidenceRules::CONTEXT_INSPECTION),
            'duplicate.target_present' => new G\DuplicateTargetPresent,
            default => throw new InvalidArgumentException("Unknown workflow guard [{$key}]."),
        };
    }

    public function effect(string $key): Effect
    {
        $assign = fn (string $mode) => new E\Assign($this->app->make(AssignmentManager::class), $this->app->make(AssigneeResolver::class), $mode);
        $notify = fn (string $mode) => new E\Notify($this->app->make(WorkflowNotifier::class), $mode);
        $sla = fn (string $mode) => new E\Sla($this->app->make(SlaService::class), $mode);

        return match ($key) {
            'inspection.record' => $this->app->make(E\RecordInspection::class),
            'repair_attempt.create' => new E\CreateRepairAttempt,
            'repair_attempt.accept' => new E\DecideRepairAttempt('accept'),
            'repair_attempt.reject' => new E\DecideRepairAttempt('reject'),
            'repair_attempt.approve' => new E\DecideRepairAttempt('approve'),
            'route.recheck_maintenance' => $this->app->make(E\RecheckMaintenance::class),
            'assign.step_role' => $assign('step_role'),
            'assign.contractor' => $assign('contractor'),
            'assign.keep' => $assign('keep'),
            'assign.end_all' => $assign('end'),
            'sla.start' => $sla('start'),
            'sla.close_current' => $sla('close'),
            'report.close' => new E\CloseReport,
            'report.link_duplicate' => new E\LinkDuplicate,
            'notify.assignees' => $notify('assignees'),
            'notify.reporter' => $notify('reporter'),
            'notify.contractor' => $notify('contractor'),
            default => throw new InvalidArgumentException("Unknown workflow effect [{$key}]."),
        };
    }

    /** @return list<string> */
    public static function knownKeys(): array
    {
        return [
            'assignee.is_actor', 'actor.mapped_engineer', 'location.within_repair_radius', 'location.within_review_radius',
            'location.validation_if_required', 'evidence.repair_requirements', 'evidence.inspection_requirements', 'duplicate.target_present',
            'inspection.record', 'repair_attempt.create', 'repair_attempt.accept', 'repair_attempt.reject', 'repair_attempt.approve',
            'route.recheck_maintenance', 'assign.step_role', 'assign.contractor', 'assign.keep', 'assign.end_all', 'sla.start',
            'sla.close_current', 'report.close', 'report.link_duplicate', 'notify.assignees', 'notify.reporter', 'notify.contractor',
        ];
    }
}
