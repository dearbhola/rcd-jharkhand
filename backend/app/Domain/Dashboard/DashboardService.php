<?php

namespace App\Domain\Dashboard;

use App\Domain\Reporting\ReportStatus;
use App\Enums\RoleCode;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Contractor;
use App\Models\NotificationDelivery;
use App\Models\RepairAttempt;
use App\Models\Report;
use App\Models\ResponsibilityAssignment;
use App\Models\Road;
use App\Models\RoadSection;
use App\Models\SlaInstance;
use App\Models\User;
use App\Models\WorkflowAssignment;
use App\Support\TestDataMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Role dashboards (§35). Every number is a query over workflow/report records; nothing is
 * stored or edited by hand. Results are cached briefly per user, role and test-data mode.
 */
class DashboardService
{
    private const TTL_SECONDS = 60;

    private const REVIEW_STEPS = [ReportStatus::JE_REVIEW, ReportStatus::AE_REVIEW, ReportStatus::EE_APPROVAL];

    public function __construct(private readonly TestDataMode $testData) {}

    /** Dashboards a user can open, highest role first. @return list<RoleCode> */
    public function available(User $user): array
    {
        $map = [RoleCode::SUPER_ADMIN, RoleCode::ADMIN, RoleCode::EE, RoleCode::AE, RoleCode::JE, RoleCode::CONTRACTOR, RoleCode::RCD_STAFF, RoleCode::CITIZEN];

        return array_values(array_filter($map, fn (RoleCode $r) => $user->hasRole($r)));
    }

    /** @return array<string, mixed> */
    public function build(User $user, RoleCode $role): array
    {
        $key = sprintf('dashboard:%d:%s:%d', $user->id, $role->value, (int) $this->testData->includesTestData());

        // Task lists are always live (they drive work); aggregates may be up to a minute old.
        $aggregates = Cache::remember($key, self::TTL_SECONDS, fn () => match ($role) {
            RoleCode::SUPER_ADMIN, RoleCode::ADMIN => $this->admin(),
            RoleCode::EE => $this->engineer($user, 'ee_user_id') + $this->ee($user),
            RoleCode::AE => $this->engineer($user, 'ae_user_id') + $this->ae($user),
            RoleCode::JE => $this->engineer($user, 'je_user_id') + $this->je($user),
            RoleCode::CONTRACTOR => $this->contractor($user),
            default => $this->reporter($user),
        });

        return $aggregates + ['tasks' => $this->tasks($user), 'generated_at' => now()];
    }

    /* ---------- shared ---------- */

    /** Open tasks of the user with SLA. @return Collection<int, array{assignment: WorkflowAssignment, report: Report, sla: ?SlaInstance}> */
    public function tasks(User $user): Collection
    {
        $assignments = WorkflowAssignment::query()
            ->where('user_id', $user->id)->where('is_active', true)
            ->whereHas('instance', fn ($q) => $q->where('status', 'active'))
            ->with(['step:id,code,name', 'originalUser:id,name', 'instance.report' => fn ($q) => $q->with(['road:id,code', 'category:id,name', 'severity:id,name,color'])])
            ->get()
            ->filter(fn ($a) => $a->instance?->report);

        $sla = SlaInstance::whereIn('workflow_instance_id', $assignments->pluck('workflow_instance_id'))->whereNull('completed_at')->get()->keyBy('workflow_instance_id');

        return $assignments
            ->map(fn ($a) => ['assignment' => $a, 'report' => $a->instance->report, 'sla' => $sla[$a->workflow_instance_id] ?? null])
            ->sortBy(fn ($t) => $t['sla']?->due_at?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /** Reports where the user is the responsible JE/AE/EE (current responsibility). */
    private function jurisdiction(User $user, string $column): Builder
    {
        return Report::query()->whereHas('currentResponsibility', fn ($r) => $r->where($column, $user->id));
    }

    private function overdueCount(Builder $reports): int
    {
        return SlaInstance::whereNull('completed_at')->where('due_at', '<', now())
            ->whereIn('report_id', (clone $reports)->select('reports.id'))->count();
    }

    /** @return array<string, int> open reports per status (non-terminal) */
    private function byStatus(Builder $reports): array
    {
        $counts = (clone $reports)->whereNotIn('status', ReportStatus::terminal())
            ->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->all();

        return collect(ReportStatus::labels())->keys()->reject(fn ($s) => in_array($s, ReportStatus::terminal(), true))
            ->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all();
    }

    /** Sections currently mapped to the officer, grouped by road. */
    private function myRoads(User $user, string $role): array
    {
        $sectionIds = ResponsibilityAssignment::where('scope_type', ResponsibilityAssignment::SCOPE_SECTION)
            ->where('role_code', $role)->where('user_id', $user->id)->effectiveOn(now())->pluck('scope_id');

        $roads = RoadSection::with('road:id,code,name')->whereIn('id', $sectionIds)->get()
            ->groupBy('road_id')
            ->map(fn ($sections) => ['road' => $sections->first()->road, 'sections' => $sections->count(),
                'km' => round($sections->sum('length_m') / 1000, 1)])
            ->filter(fn ($r) => $r['road'])->sortBy(fn ($r) => $r['road']->code)->values();

        return ['roads' => $roads, 'section_count' => $sectionIds->count()];
    }

    /* ---------- engineers ---------- */

    private function engineer(User $user, string $column): array
    {
        $reports = $this->jurisdiction($user, $column);

        return [
            'by_status' => $this->byStatus($reports),
            'open' => (clone $reports)->whereNotIn('status', ReportStatus::terminal())->count(),
            'overdue' => $this->overdueCount($reports),
            'new_week' => (clone $reports)->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    private function je(User $user): array
    {
        return [
            'my_roads' => $this->myRoads($user, 'JE'),
            'rejected_by_me' => RepairAttempt::where('rejected_by', $user->id)->where('decided_at', '>=', now()->subDays(30))->count(),
            'delegated' => WorkflowAssignment::where('user_id', $user->id)->where('is_active', true)->where('assigned_via', WorkflowAssignment::VIA_DELEGATION)->count(),
            'citizen_waiting' => WorkflowAssignment::where('user_id', $user->id)->where('is_active', true)
                ->whereHas('step', fn ($s) => $s->where('code', ReportStatus::PENDING_VALIDATION))
                ->whereHas('instance.report', fn ($r) => $r->where('reporter_role_code', 'CITIZEN'))->count(),
        ];
    }

    private function ae(User $user): array
    {
        return [
            'my_roads' => $this->myRoads($user, 'AE'),
            'rejected_by_me' => RepairAttempt::where('rejected_by', $user->id)->where('decided_at', '>=', now()->subDays(30))->count(),
            'forwarded_to_ee' => $this->jurisdiction($user, 'ae_user_id')->where('status', ReportStatus::EE_APPROVAL)->count(),
        ];
    }

    private function ee(User $user): array
    {
        $reports = $this->jurisdiction($user, 'ee_user_id');
        $ids = (clone $reports)->select('reports.id');

        return [
            'by_severity' => (clone $reports)->whereNotIn('status', ReportStatus::terminal())
                ->join('severities', 'severities.id', '=', 'reports.severity_id')
                ->selectRaw('severities.name, severities.color, severities.`rank`, COUNT(*) AS n')
                ->groupBy('severities.name', 'severities.color', 'severities.rank')->orderByDesc('severities.rank')->get(),
            'approved_30d' => (clone $reports)->where('status', ReportStatus::CLOSED)->where('closed_at', '>=', now()->subDays(30))->count(),
            'ee_rejected_30d' => RepairAttempt::whereIn('report_id', $ids)->where('rejected_stage', 'EE')->where('decided_at', '>=', now()->subDays(30))->count(),
            'trend' => $this->monthlyTrend($reports, 6),
            'contractors' => $this->contractorSummary($reports),
        ];
    }

    /** Reported vs closed per month (last N months, oldest first). */
    private function monthlyTrend(Builder $reports, int $months): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);
        $reported = (clone $reports)->where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS n")->groupBy('m')->pluck('n', 'm');
        $closed = (clone $reports)->where('status', ReportStatus::CLOSED)->where('closed_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(closed_at, '%Y-%m') AS m, COUNT(*) AS n")->groupBy('m')->pluck('n', 'm');

        return collect(range(0, $months - 1))->map(function ($i) use ($from, $reported, $closed) {
            $m = $from->copy()->addMonths($i);

            return ['month' => $m->format('M Y'), 'reported' => (int) ($reported[$m->format('Y-m')] ?? 0), 'closed' => (int) ($closed[$m->format('Y-m')] ?? 0)];
        })->all();
    }

    /** Per contractor within the jurisdiction: open tasks, overdue, rejections (30 d), closed. */
    private function contractorSummary(Builder $reports): Collection
    {
        $ids = (clone $reports)->pluck('reports.id');
        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = DB::table('report_responsibilities as rr')
            ->join('reports as r', 'r.id', '=', 'rr.report_id')
            ->whereIn('rr.report_id', $ids)->where('rr.is_current', true)->whereNotNull('rr.contractor_id')
            ->groupBy('rr.contractor_id')
            ->selectRaw('rr.contractor_id, SUM(r.status NOT IN (?, ?, ?)) AS open_n, SUM(r.status = ?) AS closed_n',
                [...ReportStatus::terminal(), ReportStatus::CLOSED])
            ->get()->keyBy('contractor_id');

        $overdue = SlaInstance::whereIn('report_id', $ids)->whereNull('completed_at')->where('due_at', '<', now())->whereNotNull('contractor_id')
            ->selectRaw('contractor_id, COUNT(*) AS n')->groupBy('contractor_id')->pluck('n', 'contractor_id');
        $rejections = RepairAttempt::whereIn('report_id', $ids)->where('outcome', RepairAttempt::OUTCOME_REJECTED)
            ->selectRaw('contractor_id, COUNT(*) AS n')->groupBy('contractor_id')->pluck('n', 'contractor_id');

        return Contractor::whereIn('id', $rows->keys())->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => [
                'contractor' => $c,
                'open' => (int) $rows[$c->id]->open_n,
                'closed' => (int) $rows[$c->id]->closed_n,
                'overdue' => (int) ($overdue[$c->id] ?? 0),
                'rejections' => (int) ($rejections[$c->id] ?? 0),
            ])
            ->sortByDesc(fn ($r) => [$r['overdue'], $r['open']])->values();
    }

    /* ---------- contractor ---------- */

    private function contractor(User $user): array
    {
        $cid = $user->contractor_id ?? 0;
        $reports = Report::query()->whereHas('currentResponsibility', fn ($r) => $r->where('contractor_id', $cid))
            ->whereNotIn('status', [ReportStatus::PENDING_VALIDATION, ReportStatus::INVALID_CLOSED, ReportStatus::MERGED_DUPLICATE]);
        $count = fn (array $statuses) => (clone $reports)->whereIn('status', $statuses)->count();

        return [
            'buckets' => [
                ['New', $count([ReportStatus::ASSIGNED]), 'inbox', 'primary', ReportStatus::ASSIGNED],
                ['In progress', $count([ReportStatus::IN_PROGRESS]), 'tools', 'primary', ReportStatus::IN_PROGRESS],
                ['Awaiting inspection', $count(self::REVIEW_STEPS), 'search', 'info', null],
                ['Reopened', $count([ReportStatus::REOPENED]), 'arrow-counterclockwise', 'danger', ReportStatus::REOPENED],
                ['Rejected (30 days)', RepairAttempt::where('contractor_id', $cid)->where('outcome', RepairAttempt::OUTCOME_REJECTED)->where('decided_at', '>=', now()->subDays(30))->count(), 'x-octagon', 'danger', null],
                ['Overdue', SlaInstance::where('contractor_id', $cid)->whereNull('completed_at')->where('due_at', '<', now())->count(), 'stopwatch', 'danger', null],
                ['Completed', $count([ReportStatus::CLOSED]), 'check2-circle', 'success', ReportStatus::CLOSED],
            ],
            'contractor' => Contractor::find($cid),
            'active_contracts' => Contract::where('contractor_id', $cid)->maintenanceActiveOn(now())->count(),
            'recent_rejections' => RepairAttempt::with('report:id,report_no')->where('contractor_id', $cid)
                ->where('outcome', RepairAttempt::OUTCOME_REJECTED)->latest('decided_at')->limit(5)->get(),
        ];
    }

    /* ---------- citizen / staff ---------- */

    private function reporter(User $user): array
    {
        $mine = Report::where('reporter_id', $user->id);

        return [
            'submitted' => (clone $mine)->count(),
            'open' => (clone $mine)->whereNotIn('status', ReportStatus::terminal())->count(),
            'closed' => (clone $mine)->where('status', ReportStatus::CLOSED)->count(),
            'not_accepted' => (clone $mine)->whereIn('status', [ReportStatus::INVALID_CLOSED, ReportStatus::MERGED_DUPLICATE])->count(),
            'recent' => (clone $mine)->with(['road:id,code', 'category:id,name', 'severity:id,name,color'])->latest('id')->limit(10)->get(),
        ];
    }

    /* ---------- admin ---------- */

    private function admin(): array
    {
        $all = Report::query();

        return [
            'masters' => [
                ['Roads', Road::count(), 'signpost-split', 'roads.index'],
                ['Road sections', RoadSection::count(), 'distribute-horizontal', 'roads.index'],
                ['Assets', Asset::count(), 'bricks', 'assets.index'],
                ['Contractors', Contractor::count(), 'building', 'contractors.index'],
                ['Contracts in maintenance', Contract::maintenanceActiveOn(now())->count(), 'file-earmark-check', 'contracts.index'],
                ['Active users', User::where('status', 'active')->count(), 'people', 'admin.users.index'],
            ],
            'by_status' => $this->byStatus($all),
            'open' => (clone $all)->whereNotIn('status', ReportStatus::terminal())->count(),
            'overdue' => $this->overdueCount($all),
            'gaps' => (clone $all)->whereNotIn('status', ReportStatus::terminal())->whereJsonContains('location_flags', 'responsibility_gap')->count(),
            'unmapped_sections' => RoadSection::where('status', 'active')->whereNotIn('id', ResponsibilityAssignment::where('scope_type', 'road_section')
                ->effectiveOn(now())->select('scope_id')->groupBy('scope_id')->havingRaw('COUNT(DISTINCT role_code) = 3'))->count(),
            'roads_without_geometry' => Road::where('status', 'active')->whereDoesntHave('currentGeometry')->count(),
            'failed_deliveries' => NotificationDelivery::where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
            'recent_audit' => AuditLog::when(! $this->testData->includesTestData(), fn ($q) => $q->where('is_test', false))->latest('id')->limit(8)->get(),
        ];
    }
}
