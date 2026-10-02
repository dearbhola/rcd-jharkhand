<?php

namespace App\Domain\Performance;

use App\Domain\Reporting\ReportStatus;
use App\Models\Contract;
use App\Models\ContractRoadSection;
use App\Models\RepairAttempt;
use App\Models\Report;
use App\Models\Road;
use App\Models\SlaRule;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Contractor performance (§37–38), derived only from system records.
 *
 * Every metric is defined by the query of the records it counts; the shown value is the
 * size (or average) of exactly that set, and the drill-down lists the same set. Values
 * cannot be edited by anyone.
 *
 * A "repair task" is a report that was actually handed to a contractor (it has a contractor
 * workflow assignment) and whose responsibility named that contractor.
 */
class PerformanceService
{
    /** key => [label, kind, description] — kind: reports | attempts | contracts | roads | percent | hours */
    public const METRICS = [
        'tasks_total' => ['Repair tasks', 'reports', 'Reports handed to the contractor for repair'],
        'tasks_completed' => ['Completed (approved)', 'reports', 'Tasks approved by the EE and closed'],
        'tasks_open' => ['Open tasks', 'reports', 'Tasks still with the contractor workflow'],
        'tasks_overdue' => ['Overdue tasks', 'reports', 'Open tasks whose contractor-stage SLA has passed'],
        'sla_breaches' => ['SLA breaches', 'reports', 'Tasks where the contractor response or repair SLA was breached (incl. overdue now)'],
        'sla_compliance' => ['SLA compliance', 'percent', 'Completed contractor-stage timers finished on time'],
        'avg_response_hours' => ['Avg. response time', 'hours', 'Assignment → contractor starts the repair'],
        'avg_repair_hours' => ['Avg. repair time', 'hours', 'Assignment → first repair submitted'],
        'repair_attempts' => ['Repair attempts', 'attempts', 'All repair submissions'],
        'rejections_je' => ['JE rejections', 'attempts', 'Repair attempts rejected by the JE'],
        'rejections_ae' => ['AE rejections', 'attempts', 'Repair attempts rejected by the AE'],
        'rejections_ee' => ['EE rejections', 'attempts', 'Repair attempts rejected by the EE'],
        'reopened_repairs' => ['Reopened repairs', 'reports', 'Tasks reopened at least once after rejection'],
        'repeat_defects' => ['Repeat defects', 'reports', 'Same defect reported again at the same place after a repair was approved'],
        'contracts_total' => ['Contracts', 'contracts', 'Contracts held'],
        'contracts_active' => ['Active contracts', 'contracts', 'In maintenance today'],
        'contracts_completed' => ['Completed contracts', 'contracts', 'Completed or maintenance period ended'],
        'roads_maintained' => ['Roads maintained', 'roads', 'Roads covered by the contractor\'s contracts'],
    ];

    private const CONTRACTOR_STAGES = [SlaRule::STAGE_RESPONSE, SlaRule::STAGE_REPAIR];

    public function __construct(private readonly Settings $settings) {}

    /** @return array<string, float|int|null> all metric values for a scope */
    public function metrics(PerformanceScope $s): array
    {
        return collect(array_keys(self::METRICS))->mapWithKeys(fn ($k) => [$k => $this->value($k, $s)])->all();
    }

    public function value(string $metric, PerformanceScope $s): float|int|null
    {
        return match (self::METRICS[$metric][1] ?? null) {
            'reports', 'attempts', 'contracts', 'roads' => $this->records($metric, $s)->count(),
            'percent' => $this->slaCompliance($s),
            'hours' => $this->averageHours($metric, $s),
            default => throw new \InvalidArgumentException("Unknown metric {$metric}"),
        };
    }

    /** Query of the exact records behind a count metric (or the population of an average/percentage). */
    public function records(string $metric, PerformanceScope $s): Builder
    {
        $tasks = fn () => $this->tasks($s);

        return match ($metric) {
            'tasks_total' => $tasks(),
            'tasks_completed' => $tasks()->where('reports.status', ReportStatus::CLOSED),
            'tasks_open' => $tasks()->whereNotIn('reports.status', ReportStatus::terminal())
                ->whereHas('currentResponsibility', fn ($r) => $r->where('route', 'contractor')),
            'tasks_overdue' => $tasks()->whereNotIn('reports.status', ReportStatus::terminal())
                ->whereHas('slaInstances', fn ($q) => $q->whereIn('stage', self::CONTRACTOR_STAGES)->whereNull('completed_at')->where('due_at', '<', now())),
            // Breached = recorded breach, or still open past its due time.
            'sla_breaches' => $tasks()->whereHas('slaInstances', fn ($q) => $q->whereIn('stage', self::CONTRACTOR_STAGES)
                ->where(fn ($w) => $w->whereNotNull('breached_at')->orWhere(fn ($o) => $o->whereNull('completed_at')->where('due_at', '<', now())))),
            'sla_compliance', 'avg_response_hours', 'avg_repair_hours' => $tasks()->whereHas('slaInstances', fn ($q) => $q->where('stage', SlaRule::STAGE_RESPONSE)),
            'repair_attempts' => $this->attempts($s),
            'rejections_je' => $this->attempts($s)->where('outcome', RepairAttempt::OUTCOME_REJECTED)->where('rejected_stage', 'JE'),
            'rejections_ae' => $this->attempts($s)->where('outcome', RepairAttempt::OUTCOME_REJECTED)->where('rejected_stage', 'AE'),
            'rejections_ee' => $this->attempts($s)->where('outcome', RepairAttempt::OUTCOME_REJECTED)->where('rejected_stage', 'EE'),
            'reopened_repairs' => $tasks()->whereHas('repairAttempts', fn ($a) => $a->where('outcome', RepairAttempt::OUTCOME_REJECTED)
                ->when($s->contractorId, fn ($q) => $q->where('contractor_id', $s->contractorId))),
            'repeat_defects' => $this->repeats($tasks()),
            'contracts_total' => $this->contracts($s),
            'contracts_active' => $this->contracts($s)->maintenanceActiveOn(now()),
            'contracts_completed' => $this->contracts($s)->where(fn ($q) => $q->where('status', 'completed')->orWhereDate('maintenance_end_date', '<', now())),
            'roads_maintained' => Road::query()->whereIn('id', ContractRoadSection::query()
                ->whereIn('contract_id', $this->contracts($s)->select('contracts.id'))
                ->when($s->roadId, fn ($q) => $q->where('road_id', $s->roadId))
                ->when($s->from, fn ($q) => $q->where(fn ($w) => $w->whereNull('effective_to')->orWhereDate('effective_to', '>=', $s->from)))
                ->when($s->to, fn ($q) => $q->whereDate('effective_from', '<=', $s->to))
                ->select('road_id')),
            default => throw new \InvalidArgumentException("Unknown metric {$metric}"),
        };
    }

    /**
     * Per-record figures for average metrics (hours), keyed by report id — the drill-down shows them.
     *
     * @return Collection<int, float>
     */
    public function hoursPerReport(string $metric, PerformanceScope $s): Collection
    {
        $ids = $this->records($metric, $s)->pluck('reports.id');
        if ($ids->isEmpty()) {
            return collect();
        }

        $assigned = DB::table('sla_instances')->whereIn('report_id', $ids)->where('stage', SlaRule::STAGE_RESPONSE)
            ->groupBy('report_id')->selectRaw('report_id, MIN(started_at) AS t')->pluck('t', 'report_id');

        $end = $metric === 'avg_response_hours'
            ? DB::table('sla_instances')->whereIn('report_id', $ids)->where('stage', SlaRule::STAGE_RESPONSE)->whereNotNull('completed_at')
                ->groupBy('report_id')->selectRaw('report_id, MIN(completed_at) AS t')->pluck('t', 'report_id')
            : DB::table('repair_attempts')->whereIn('report_id', $ids)
                ->when($s->contractorId, fn ($q) => $q->where('contractor_id', $s->contractorId))
                ->groupBy('report_id')->selectRaw('report_id, MIN(submitted_at) AS t')->pluck('t', 'report_id');

        return $end->filter(fn ($t, $id) => isset($assigned[$id]))
            ->map(fn ($t, $id) => round((strtotime($t) - strtotime($assigned[$id])) / 3600, 1));
    }

    /** Completed contractor-stage timers on time / completed, in %. */
    private function slaCompliance(PerformanceScope $s): ?float
    {
        $row = DB::table('sla_instances')
            ->whereIn('report_id', $this->tasks($s)->select('reports.id'))
            ->whereIn('stage', self::CONTRACTOR_STAGES)->whereNotNull('completed_at')
            ->when($s->contractorId, fn ($q) => $q->where('contractor_id', $s->contractorId))
            ->selectRaw('COUNT(*) AS n, SUM(breached_at IS NULL) AS ok')->first();

        return $row && $row->n ? round($row->ok / $row->n * 100, 1) : null;
    }

    private function averageHours(string $metric, PerformanceScope $s): ?float
    {
        $hours = $this->hoursPerReport($metric, $s);

        return $hours->isEmpty() ? null : round($hours->avg(), 1);
    }

    /** Reports handed to a contractor (optionally the scoped one), within the scope. */
    public function tasks(PerformanceScope $s): Builder
    {
        return Report::query()
            ->whereHas('responsibilities', fn ($r) => $r->where('route', 'contractor')
                ->when($s->contractorId, fn ($q) => $q->where('contractor_id', $s->contractorId))
                ->when($s->contractId, fn ($q) => $q->where('contract_id', $s->contractId)))
            ->whereHas('workflowInstances.assignments', fn ($a) => $a->where('role_code', 'CONTRACTOR'))
            ->when($s->divisionId, fn ($q) => $q->where('reports.division_id', $s->divisionId))
            ->when($s->roadId, fn ($q) => $q->where('reports.road_id', $s->roadId))
            ->when($s->sectionId, fn ($q) => $q->where('reports.road_section_id', $s->sectionId))
            ->when($s->from, fn ($q) => $q->where('reports.created_at', '>=', $s->from))
            ->when($s->to, fn ($q) => $q->where('reports.created_at', '<=', $s->to));
    }

    private function attempts(PerformanceScope $s): Builder
    {
        return RepairAttempt::query()
            ->whereIn('report_id', $this->tasks($s)->select('reports.id'))
            ->when($s->contractorId, fn ($q) => $q->where('contractor_id', $s->contractorId));
    }

    private function contracts(PerformanceScope $s): Builder
    {
        return Contract::query()
            ->when($s->contractorId, fn ($q) => $q->where('contractor_id', $s->contractorId))
            ->when($s->contractId, fn ($q) => $q->whereKey($s->contractId))
            ->when($s->divisionId, fn ($q) => $q->where('division_id', $s->divisionId))
            ->when($s->roadId, fn ($q) => $q->whereHas('roadSections', fn ($m) => $m->where('road_id', $s->roadId)))
            ->when($s->from, fn ($q) => $q->where(fn ($w) => $w->whereNull('maintenance_end_date')->orWhereDate('maintenance_end_date', '>=', $s->from)))
            ->when($s->to, fn ($q) => $q->where(fn ($w) => $w->whereNull('maintenance_start_date')->orWhereDate('maintenance_start_date', '<=', $s->to)));
    }

    /**
     * Repeat defects: the same category reported again on the same road, near the same chainage,
     * within a window after an earlier report there was approved and closed.
     */
    private function repeats(Builder $tasks): Builder
    {
        $window = $this->settings->int('performance.repeat_window_days');
        $metres = $this->settings->int('performance.repeat_chainage_m');

        return $tasks->whereExists(fn ($q) => $q->from('reports as prev')
            ->whereColumn('prev.road_id', 'reports.road_id')
            ->whereColumn('prev.issue_category_id', 'reports.issue_category_id')
            ->where('prev.status', ReportStatus::CLOSED)
            ->whereColumn('prev.id', '!=', 'reports.id')
            ->whereColumn('prev.closed_at', '<', 'reports.created_at')
            ->whereRaw('reports.created_at <= DATE_ADD(prev.closed_at, INTERVAL ? DAY)', [$window])
            ->whereRaw('ABS(CAST(prev.chainage_m AS SIGNED) - CAST(reports.chainage_m AS SIGNED)) <= ?', [$metres]));
    }
}
