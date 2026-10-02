{{-- $tasks: collection from DashboardService::tasks(); $title, $limit optional --}}
@php($overdue = $tasks->filter(fn ($t) => $t['sla'] && $t['sla']->due_at->isPast())->count())
<div class="card h-100">
    <div class="card-header d-flex justify-content-between">
        <span>{{ $title ?? 'My tasks' }} <span class="badge text-bg-secondary">{{ $tasks->count() }}</span>
            @if ($overdue)<span class="badge text-bg-danger">{{ $overdue }} overdue</span>@endif</span>
        <a href="{{ route('tasks.index') }}" class="small">All tasks</a>
    </div>
    <div class="list-group list-group-flush">
        @forelse ($tasks->take($limit ?? 8) as $t)
            @php($r = $t['report'])
            <a href="{{ route('reports.show', $r) }}" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between gap-2">
                    <span class="fw-semibold text-truncate">{{ $r->report_no }} <x-test-badge :model="$r" /></span>
                    @if ($t['sla'])
                        <span class="small text-nowrap {{ $t['sla']->due_at->isPast() ? 'text-danger fw-semibold' : 'text-muted' }}">
                            <i class="bi bi-stopwatch" aria-hidden="true"></i> {{ $t['sla']->due_at->isPast() ? 'Overdue '.$t['sla']->due_at->diffForHumans(null, true) : 'Due '.$t['sla']->due_at->diffForHumans() }}</span>
                    @endif
                </div>
                <div class="small text-muted">{{ $t['assignment']->step->name }} · {{ $r->category->name }} · {{ $r->road?->code }} km {{ km($r->chainage_m) }}
                    @if ($t['assignment']->originalUser)<span class="badge text-bg-light border">for {{ $t['assignment']->originalUser->name }}</span>@endif
                </div>
            </a>
        @empty
            <div class="list-group-item text-muted text-center py-4"><i class="bi bi-check2-all" aria-hidden="true"></i> Nothing waiting for you.</div>
        @endforelse
    </div>
</div>
