<?php

namespace App\Domain\Performance;

use App\Domain\Analytics\Table;
use App\Domain\Reporting\ReportStatus;
use App\Models\Contractor;
use App\Models\SlaInstance;
use App\Models\SlaRule;

/**
 * The records behind a performance figure (§38: "28 reopened repairs must be clickable and show
 * the underlying 28 reports"). Uses the same query as the figure itself.
 */
class DrillDown
{
    public function __construct(private readonly PerformanceService $performance) {}

    public function table(string $metric, PerformanceScope $s, bool $includesTest, array $filterLabels): Table
    {
        [$label, $kind, $description] = PerformanceService::METRICS[$metric] ?? abort(404);
        $title = $label.($s->contractorId ? ' — '.Contractor::withTrashed()->find($s->contractorId)?->name : '');
        $value = $this->performance->value($metric, $s);
        $subtitle = $description.' · value: '.($value === null ? '—' : $value.($kind === 'percent' ? ' %' : ($kind === 'hours' ? ' h' : '')));

        [$columns, $rows] = match ($kind) {
            'reports' => $this->reports($metric, $s),
            'hours' => $this->hours($metric, $s),
            'percent' => $this->timers($s),
            'attempts' => $this->attempts($metric, $s),
            'contracts' => $this->contracts($metric, $s),
            'roads' => $this->roads($metric, $s),
        };

        return new Table($title, $columns, $rows, $filterLabels, $includesTest, subtitle: $subtitle);
    }

    private function reportRow($r): array
    {
        return [
            'report' => $r->report_no, 'url' => route('reports.show', $r->id),
            'road' => ($r->road?->code ?? '').' km '.km($r->chainage_m),
            'category' => $r->category?->name, 'severity' => $r->severity?->name,
            'status' => ReportStatus::labels()[$r->status][0] ?? $r->status, 'reported' => $r->created_at,
        ];
    }

    private const REPORT_COLUMNS = ['report' => 'Report', 'road' => 'Road / km', 'category' => 'Damage', 'severity' => 'Severity', 'status' => 'Status', 'reported' => 'Reported'];

    private function reports(string $metric, PerformanceScope $s): array
    {
        $rows = $this->performance->records($metric, $s)
            ->with(['road:id,code', 'category:id,name', 'severity:id,name'])->orderBy('reports.created_at')->get(['reports.*'])
            ->map(fn ($r) => $this->reportRow($r))->all();

        return [self::REPORT_COLUMNS, $rows];
    }

    private function hours(string $metric, PerformanceScope $s): array
    {
        $hours = $this->performance->hoursPerReport($metric, $s);
        $rows = $this->performance->records($metric, $s)->whereIn('reports.id', $hours->keys())
            ->with(['road:id,code', 'category:id,name', 'severity:id,name'])->orderBy('reports.created_at')->get(['reports.*'])
            ->map(fn ($r) => $this->reportRow($r) + ['hours' => $hours[$r->id]])->all();

        return [self::REPORT_COLUMNS + ['hours' => 'Hours'], $rows];
    }

    private function timers(PerformanceScope $s): array
    {
        $rows = SlaInstance::with('report:id,report_no')
            ->whereIn('report_id', $this->performance->tasks($s)->select('reports.id'))
            ->whereIn('stage', [SlaRule::STAGE_RESPONSE, SlaRule::STAGE_REPAIR])->whereNotNull('completed_at')
            ->when($s->contractorId, fn ($q) => $q->where('contractor_id', $s->contractorId))
            ->orderBy('started_at')->get()
            ->map(fn ($t) => ['report' => $t->report?->report_no, 'url' => route('reports.show', $t->report_id), 'stage' => $t->stage,
                'started' => $t->started_at, 'due' => $t->due_at, 'completed' => $t->completed_at, 'result' => $t->breached_at ? 'Late' : 'On time'])->all();

        return [['report' => 'Report', 'stage' => 'Stage', 'started' => 'Started', 'due' => 'Due', 'completed' => 'Completed', 'result' => 'Result'], $rows];
    }

    private function attempts(string $metric, PerformanceScope $s): array
    {
        $rows = $this->performance->records($metric, $s)->with(['report:id,report_no', 'contractor:id,name', 'rejecter:id,name'])
            ->orderBy('submitted_at')->get()
            ->map(fn ($a) => ['report' => $a->report?->report_no, 'url' => route('reports.show', $a->report_id), 'attempt' => $a->attempt_no,
                'contractor' => $a->contractor?->name, 'submitted' => $a->submitted_at, 'outcome' => $a->outcome,
                'stage' => $a->rejected_stage, 'by' => $a->rejecter?->name, 'reason' => $a->rejection_reason])->all();

        return [['report' => 'Report', 'attempt' => 'Attempt', 'contractor' => 'Contractor', 'submitted' => 'Submitted', 'outcome' => 'Outcome',
            'stage' => 'Rejected at', 'by' => 'Rejected by', 'reason' => 'Reason'], $rows];
    }

    private function contracts(string $metric, PerformanceScope $s): array
    {
        $rows = $this->performance->records($metric, $s)->with('contractor:id,name')->orderBy('contract_no')->get()
            ->map(fn ($c) => ['contract' => $c->contract_no, 'url' => route('contracts.show', $c), 'contractor' => $c->contractor->name,
                'maintenance' => d($c->maintenance_start_date).' – '.d($c->maintenance_end_date), 'status' => $c->status])->all();

        return [['contract' => 'Contract', 'contractor' => 'Contractor', 'maintenance' => 'Maintenance period', 'status' => 'Status'], $rows];
    }

    private function roads(string $metric, PerformanceScope $s): array
    {
        $rows = $this->performance->records($metric, $s)->orderBy('code')->get()
            ->map(fn ($r) => ['road' => $r->code, 'url' => route('roads.show', $r), 'name' => $r->name, 'length' => km($r->length_m, true)])->all();

        return [['road' => 'Road', 'name' => 'Name', 'length' => 'Length'], $rows];
    }
}
