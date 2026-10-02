<?php

namespace App\Domain\Performance;

use App\Models\Contract;
use App\Models\Evidence;
use App\Models\Inspection;

/**
 * Contract completion report (§39): the contract's performance over its maintenance period.
 * Informational only — it does not award, rank or recommend contractors for procurement.
 */
class CompletionReportService
{
    public const DISCLAIMER = 'This report records performance from system data. It does not award, rank or recommend '
        .'any contractor; use in procurement or contract decisions is subject to applicable departmental procurement rules.';

    public function __construct(private readonly PerformanceService $performance) {}

    /** @return array<string, mixed> */
    public function build(Contract $contract): array
    {
        $contract->loadMissing(['contractor', 'division']);
        $start = $contract->maintenance_start_date?->copy()->startOfDay();
        $end = $contract->maintenance_end_date?->copy()->endOfDay();
        $ended = $end && $end->isPast();

        $scope = new PerformanceScope(
            contractorId: $contract->contractor_id,
            contractId: $contract->id,
            from: $start,
            to: $end,
            periodLabel: $start ? 'Maintenance period '.$start->format('d-M-Y').' – '.$end->format('d-M-Y') : null,
        );

        $metrics = $this->performance->metrics($scope);
        $tasks = $this->performance->tasks($scope)
            ->with(['road:id,code', 'section:id,code', 'category:id,name', 'severity:id,name', 'repairAttempts:id,report_id,outcome,rejected_stage'])
            ->orderBy('reports.created_at')->get();
        $reportIds = $tasks->pluck('id');

        $evidence = Evidence::whereIn('report_id', $reportIds)
            ->selectRaw('evidenceable_type, kind, COUNT(*) AS n')->groupBy('evidenceable_type', 'kind')->get()
            ->map(fn ($r) => ['stage' => ['report' => 'Reported damage', 'repair_attempt' => 'Repair submissions', 'inspection' => 'Inspections'][$r->evidenceable_type] ?? $r->evidenceable_type,
                'kind' => ucfirst($r->kind), 'count' => (int) $r->n]);

        $inspections = Inspection::with(['inspector:id,name', 'report:id,report_no', 'repairAttempt:id,attempt_no'])
            ->whereIn('report_id', $reportIds)->where('stage', '!=', Inspection::STAGE_VALIDATION)->orderBy('inspected_at')->get();

        $open = $metrics['tasks_open'];
        $finalStatus = match (true) {
            ! $start => 'No maintenance period recorded for this contract.',
            ! $ended => sprintf('Maintenance period in progress — %d day(s) remaining. %d task(s) completed, %d open.',
                (int) now()->diffInDays($end), $metrics['tasks_completed'], $open),
            $open === 0 => sprintf('Maintenance period ended on %s. All %d repair task(s) completed.', $end->format('d-M-Y'), $metrics['tasks_completed']),
            default => sprintf('Maintenance period ended on %s with %d open repair task(s) (moved to department action where applicable).', $end->format('d-M-Y'), $open),
        };

        return [
            'contract' => $contract,
            'scope' => $scope,
            'ended' => $ended,
            'metrics' => $metrics,
            'coverage' => $contract->roadSections()->with(['road:id,code,name', 'section:id,code'])->orderBy('road_id')->orderBy('start_chainage_m')->get(),
            'tasks' => $tasks,
            'evidence' => $evidence,
            'inspections' => $inspections,
            'by_severity' => $tasks->groupBy(fn ($r) => $r->severity->name)->map->count(),
            'final_status' => $finalStatus,
            'generated_at' => now(),
            'disclaimer' => self::DISCLAIMER,
        ];
    }
}
