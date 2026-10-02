<?php

namespace App\Domain\Analytics;

use App\Domain\Reporting\ReportStatus;
use App\Http\Controllers\Admin\SlaController;
use App\Models\Contractor;
use App\Models\Division;
use App\Models\IssueCategory;
use App\Models\RepairAttempt;
use App\Models\Report;
use App\Models\Road;
use App\Models\Severity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reports module (§40). Each method returns a Table that is shown on screen and exported unchanged.
 * Reports are attributed to periods by the date they were reported.
 */
class AnalyticsService
{
    public const TYPES = [
        'daily' => ['Daily report', 'period'],
        'weekly' => ['Weekly report', 'period'],
        'monthly' => ['Monthly report', 'period'],
        'fy' => ['Financial-year report', 'period'],
        'road' => ['Road-wise report', 'group'],
        'contractor' => ['Contractor-wise report', 'group'],
        'je' => ['JE-wise report', 'group'],
        'ae' => ['AE-wise report', 'group'],
        'ee' => ['EE-wise report', 'group'],
        'division' => ['Division-wise report', 'group'],
        'category' => ['Damage-category report', 'group'],
        'severity' => ['Severity report', 'group'],
        'sla' => ['SLA report', 'sla'],
        'rejections' => ['Rejection report', 'rejections'],
        'reopenings' => ['Reopening report', 'reopenings'],
    ];

    private const COLUMNS = [
        'reported' => 'Reported', 'open' => 'Open', 'closed' => 'Closed', 'invalid' => 'Invalid / duplicate',
        'overdue' => 'Overdue now', 'rejections' => 'Repair rejections', 'avg_close_hours' => 'Avg. hours to close',
    ];

    /** @param array<string, mixed> $f from, to, division_id */
    public function build(string $type, array $f, bool $includesTest): Table
    {
        [$title, $kind] = self::TYPES[$type] ?? abort(404);
        $filters = $this->describe($f);

        return match ($kind) {
            'period' => $this->period($type, $title, $f, $filters, $includesTest),
            'group' => $this->grouped($type, $title, $f, $filters, $includesTest),
            'sla' => $this->sla($title, $f, $filters, $includesTest),
            'rejections' => $this->rejections($title, $f, $filters, $includesTest),
            'reopenings' => $this->reopenings($title, $f, $filters, $includesTest),
        };
    }

    private function base(array $f): Builder
    {
        return Report::query()
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('reports.created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('reports.created_at', '<', Carbon::parse($v)->addDay()))
            ->when($f['division_id'] ?? null, fn ($q, $v) => $q->where('reports.division_id', $v));
    }

    private function period(string $type, string $title, array $f, array $filters, bool $test): Table
    {
        $key = match ($type) {
            'daily' => "DATE_FORMAT(reports.created_at, '%Y-%m-%d')",
            'weekly' => "DATE_FORMAT(reports.created_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(reports.created_at, '%Y-%m')",
            'fy' => "CONCAT(IF(MONTH(reports.created_at) >= 4, YEAR(reports.created_at), YEAR(reports.created_at) - 1), '-', LPAD(MOD(IF(MONTH(reports.created_at) >= 4, YEAR(reports.created_at), YEAR(reports.created_at) - 1) + 1, 100), 2, '0'))",
        };

        $rows = $this->aggregate($this->base($f), $key)->sortKeysDesc()
            ->map(fn ($r, $k) => ['group' => $type === 'fy' ? "FY {$k}" : $k] + $r)->values()->all();

        return new Table($title, ['group' => 'Period'] + self::COLUMNS, $rows, $filters, $test);
    }

    private function grouped(string $type, string $title, array $f, array $filters, bool $test): Table
    {
        $base = $this->base($f);
        if (in_array($type, ['contractor', 'je', 'ae', 'ee'], true)) {
            $base->join('report_responsibilities as rr', fn ($j) => $j->on('rr.report_id', '=', 'reports.id')->where('rr.is_current', true));
        }

        $key = match ($type) {
            'road' => 'reports.road_id', 'division' => 'reports.division_id', 'category' => 'reports.issue_category_id',
            'severity' => 'reports.severity_id', 'contractor' => 'rr.contractor_id', 'je' => 'rr.je_user_id', 'ae' => 'rr.ae_user_id', 'ee' => 'rr.ee_user_id',
        };
        $stats = $this->aggregate($base, $key);

        $ids = $stats->keys()->filter()->all();
        $names = match ($type) {
            'road' => Road::withTrashed()->whereIn('id', $ids)->get()->mapWithKeys(fn ($r) => [$r->id => "{$r->code} — {$r->name}"]),
            'division' => Division::whereIn('id', $ids)->pluck('name', 'id'),
            'category' => IssueCategory::with('assetType:id,name')->whereIn('id', $ids)->get()->mapWithKeys(fn ($c) => [$c->id => "{$c->assetType->name} › {$c->name}"]),
            'severity' => Severity::whereIn('id', $ids)->pluck('name', 'id'),
            'contractor' => Contractor::withTrashed()->whereIn('id', $ids)->pluck('name', 'id'),
            default => User::withTrashed()->whereIn('id', $ids)->get()->mapWithKeys(fn ($u) => [$u->id => trim($u->name.' '.($u->employee_code ? "({$u->employee_code})" : ''))]),
        };
        $blank = $type === 'contractor' ? 'No contractor (department)' : 'Not mapped';

        $rows = $stats->map(fn ($r, $id) => ['group' => $names[$id] ?? $blank] + $r)->sortByDesc('reported')->values()->all();
        $label = ['road' => 'Road', 'division' => 'Division', 'category' => 'Damage category', 'severity' => 'Severity',
            'contractor' => 'Contractor', 'je' => 'JE', 'ae' => 'AE', 'ee' => 'EE'][$type];
        $notes = in_array($type, ['contractor', 'je', 'ae', 'ee'], true) ? ['Grouped by the current responsibility recorded on each report.'] : [];

        return new Table($title, ['group' => $label] + self::COLUMNS, $rows, $filters, $test, $notes);
    }

    /** One SQL pass per figure group, keyed by $key. @return \Illuminate\Support\Collection<string, array<string, mixed>> */
    private function aggregate(Builder $base, string $key)
    {
        [$closed, $invalid] = [ReportStatus::CLOSED, [ReportStatus::INVALID_CLOSED, ReportStatus::MERGED_DUPLICATE]];
        $terminal = ReportStatus::terminal();

        $main = (clone $base)->selectRaw("{$key} AS gkey, COUNT(*) AS reported,
                SUM(reports.status NOT IN (?, ?, ?)) AS open_n, SUM(reports.status = ?) AS closed_n, SUM(reports.status IN (?, ?)) AS invalid_n,
                AVG(CASE WHEN reports.status = ? THEN TIMESTAMPDIFF(MINUTE, reports.created_at, reports.closed_at) / 60 END) AS avg_h",
            [...$terminal, $closed, ...$invalid, $closed])
            ->groupBy('gkey')->get()->keyBy('gkey');

        $overdue = (clone $base)->join('sla_instances as si', 'si.report_id', '=', 'reports.id')
            ->whereNull('si.completed_at')->where('si.due_at', '<', now())
            ->selectRaw("{$key} AS gkey, COUNT(DISTINCT reports.id) AS n")->groupBy('gkey')->pluck('n', 'gkey');
        $rejections = (clone $base)->join('repair_attempts as ra', 'ra.report_id', '=', 'reports.id')
            ->where('ra.outcome', RepairAttempt::OUTCOME_REJECTED)
            ->selectRaw("{$key} AS gkey, COUNT(*) AS n")->groupBy('gkey')->pluck('n', 'gkey');

        return $main->map(fn ($r, $k) => [
            'reported' => (int) $r->reported,
            'open' => (int) $r->open_n,
            'closed' => (int) $r->closed_n,
            'invalid' => (int) $r->invalid_n,
            'overdue' => (int) ($overdue[$k] ?? 0),
            'rejections' => (int) ($rejections[$k] ?? 0),
            'avg_close_hours' => $r->avg_h !== null ? round((float) $r->avg_h, 1) : null,
        ]);
    }

    private function sla(string $title, array $f, array $filters, bool $test): Table
    {
        $stats = DB::table('sla_instances as si')
            ->whereIn('si.report_id', $this->base($f)->select('reports.id'))
            ->selectRaw('si.stage, COUNT(*) AS n, SUM(si.completed_at IS NOT NULL) AS done, SUM(si.completed_at IS NOT NULL AND si.breached_at IS NULL) AS on_time,
                SUM(si.breached_at IS NOT NULL OR (si.completed_at IS NULL AND si.due_at < ?)) AS breached,
                AVG(CASE WHEN si.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, si.started_at, si.completed_at) / 60 END) AS avg_h', [now()])
            ->groupBy('si.stage')->get()->keyBy('stage');

        $rows = collect(SlaController::STAGES)->map(function ($label, $stage) use ($stats) {
            $s = $stats[$stage] ?? null;

            return [
                'stage' => $label,
                'timers' => (int) ($s->n ?? 0),
                'completed' => (int) ($s->done ?? 0),
                'on_time' => (int) ($s->on_time ?? 0),
                'breached' => (int) ($s->breached ?? 0),
                'compliance' => ($s->done ?? 0) ? round($s->on_time / $s->done * 100, 1).' %' : '—',
                'avg_hours' => isset($s->avg_h) ? round((float) $s->avg_h, 1) : null,
            ];
        })->values()->all();

        return new Table($title, ['stage' => 'Stage', 'timers' => 'Timers', 'completed' => 'Completed', 'on_time' => 'On time',
            'breached' => 'Breached (incl. overdue now)', 'compliance' => 'Compliance', 'avg_hours' => 'Avg. hours'], $rows, $filters, $test,
            ['Compliance = completed on time ÷ completed.']);
    }

    private function rejections(string $title, array $f, array $filters, bool $test): Table
    {
        $rows = RepairAttempt::with(['report:id,report_no,road_id,chainage_m', 'report.road:id,code', 'contractor:id,name', 'rejecter:id,name'])
            ->whereIn('report_id', $this->base($f)->select('reports.id'))
            ->where('outcome', RepairAttempt::OUTCOME_REJECTED)->latest('decided_at')->limit(5000)->get()
            ->map(fn ($a) => [
                'date' => $a->decided_at, 'report' => $a->report?->report_no, 'road' => $a->report?->road?->code.' km '.km($a->report?->chainage_m),
                'attempt' => $a->attempt_no, 'stage' => $a->rejected_stage, 'by' => $a->rejecter?->name, 'contractor' => $a->contractor?->name, 'reason' => $a->rejection_reason,
            ])->all();

        return new Table($title, ['date' => 'Rejected on', 'report' => 'Report', 'road' => 'Road / km', 'attempt' => 'Attempt', 'stage' => 'Stage',
            'by' => 'Rejected by', 'contractor' => 'Contractor', 'reason' => 'Reason'], $rows, $filters, $test);
    }

    private function reopenings(string $title, array $f, array $filters, bool $test): Table
    {
        $rows = $this->base($f)
            ->whereHas('repairAttempts', fn ($a) => $a->where('outcome', RepairAttempt::OUTCOME_REJECTED))
            ->with(['road:id,code', 'repairAttempts:id,report_id,outcome,rejected_stage', 'currentResponsibility.contractor:id,name'])
            ->latest('reports.created_at')->limit(5000)->get()
            ->map(fn ($r) => [
                'report' => $r->report_no, 'road' => $r->road?->code.' km '.km($r->chainage_m), 'contractor' => $r->currentResponsibility?->contractor?->name,
                'attempts' => $r->repairAttempts->count(),
                'rejections' => $r->repairAttempts->where('outcome', 'rejected')->pluck('rejected_stage')->join(', '),
                'status' => ReportStatus::labels()[$r->status][0] ?? $r->status,
            ])->all();

        return new Table($title, ['report' => 'Report', 'road' => 'Road / km', 'contractor' => 'Contractor', 'attempts' => 'Repair attempts',
            'rejections' => 'Rejected at', 'status' => 'Current status'], $rows, $filters, $test);
    }

    /** @return array<string, string> */
    private function describe(array $f): array
    {
        return array_filter([
            'From' => ! empty($f['from']) ? Carbon::parse($f['from'])->format('d-M-Y') : null,
            'To' => ! empty($f['to']) ? Carbon::parse($f['to'])->format('d-M-Y') : null,
            'Division' => ! empty($f['division_id']) ? Division::find($f['division_id'])?->name : null,
        ]);
    }
}
