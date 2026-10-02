<x-app-layout title="My tasks" :breadcrumbs="['My tasks']">
    <x-page-header title="My tasks" :subtitle="$total.' open task(s)'.($overdue ? ' · '.$overdue.' overdue' : '')" />

    @forelse ($groups as $stepName => $rows)
        <div class="card mb-3">
            <div class="card-header">{{ $stepName }} <span class="badge text-bg-secondary">{{ $rows->count() }}</span></div>
            <div class="list-group list-group-flush">
                @foreach ($rows as $row)
                    @php($r = $row['report'])
                    <a href="{{ route('reports.show', $r) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between flex-wrap gap-1">
                            <span class="fw-semibold">{{ $r->report_no }} <x-test-badge :model="$r" /></span>
                            @if ($row['sla'])
                                <span class="small {{ $row['sla']->due_at->isPast() ? 'text-danger fw-semibold' : 'text-muted' }}">
                                    <i class="bi bi-stopwatch"></i> {{ $row['sla']->due_at->isPast() ? 'Overdue '.$row['sla']->due_at->diffForHumans(null, true) : 'Due '.$row['sla']->due_at->diffForHumans() }}
                                </span>
                            @endif
                        </div>
                        <div class="small">{{ $r->category->name }} <x-severity-badge :severity="$r->severity" /></div>
                        <div class="small text-muted">{{ $r->road?->code }} / {{ $r->section?->code }} · km {{ km($r->chainage_m) }} · assigned {{ $row['assignment']->assigned_at->diffForHumans() }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-check2-all fs-1 d-block mb-2"></i>No open tasks.</div></div>
    @endforelse
</x-app-layout>
