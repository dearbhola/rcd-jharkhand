<div class="card">
    <div class="card-header d-flex justify-content-between">Workflow history <x-report-status :status="$report->status" /></div>
    <ul class="list-group list-group-flush small">
        @if ($holders->isNotEmpty())
            <li class="list-group-item bg-light"><i class="bi bi-person-check me-1"></i>With: {{ $holders->map(fn ($h) => $h->user->name.($h->originalUser ? ' (for '.$h->originalUser->name.')' : ''))->join(', ') }}
                <span class="text-muted">since {{ d($holders->first()->assigned_at, true) }}</span></li>
        @endif
        <li class="list-group-item"><span class="fw-semibold">Reported</span> by {{ $report->reporter->name }} <span class="text-muted">· {{ d($report->received_at, true) }}</span></li>
        @foreach ($timeline as $a)
            <li class="list-group-item">
                <span class="fw-semibold">{{ $a->fromStep?->name }} → {{ $a->toStep->name }}</span>
                <div class="text-muted">
                    {{ $a->actor?->name ?? 'System' }}{{ $a->actor_role_code ? ' ('.$a->actor_role_code.')' : '' }} · {{ d($a->created_at, true) }}
                    @if (isset($a->payload['distance_from_site_m'])) · {{ round($a->payload['distance_from_site_m']) }} m from site @endif
                    @if (in_array('location_override', $a->payload['flags'] ?? [], true))<span class="badge text-bg-dark">override</span>@endif
                </div>
                @if ($a->comment)<div>“{{ $a->comment }}”</div>@endif
            </li>
        @endforeach
        @foreach ($report->links as $link)
            <li class="list-group-item">Merged as duplicate of <a href="{{ route('reports.show', $link->linked_report_id) }}">{{ $link->linkedReport?->report_no }}</a></li>
        @endforeach
    </ul>
</div>
