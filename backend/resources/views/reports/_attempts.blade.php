@php($outcomes = ['pending' => ['Awaiting review', 'warning'], 'accepted_je' => ['Accepted by JE', 'info'], 'accepted_ae' => ['Accepted by AE', 'info'], 'rejected' => ['Rejected', 'danger'], 'approved' => ['Approved by EE', 'success']])
@if ($report->repairAttempts->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header">Repair attempts <span class="text-muted small fw-normal">({{ $report->repairAttempts->count() }}) — every attempt is preserved</span></div>
        <div class="list-group list-group-flush">
            @foreach ($report->repairAttempts as $a)
                @php([$label, $colour] = $outcomes[$a->outcome] ?? [$a->outcome, 'secondary'])
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="fw-semibold">Attempt {{ $a->attempt_no }} <span class="badge text-bg-{{ $colour }}">{{ $label }}{{ $a->rejected_stage ? ' ('.$a->rejected_stage.')' : '' }}</span></div>
                        <div class="small text-muted">{{ d($a->submitted_at, true) }}</div>
                    </div>
                    <div class="small text-muted">{{ $a->contractor?->name }} · submitted by {{ $a->submitter->name }}</div>
                    <div class="small mt-1">{{ $a->description }}</div>
                    @if ($a->comments)<div class="small text-muted">“{{ $a->comments }}”</div>@endif
                    @if ($a->rejection_reason)
                        <div class="alert alert-danger small py-1 px-2 mt-2 mb-0"><strong>Rejected by {{ $a->rejected_stage }} {{ $a->rejecter?->name }}:</strong> {{ $a->rejection_reason }}</div>
                    @endif
                    @include('reports._evidence-thumbs', ['items' => $a->evidences])
                    @foreach ($a->inspections as $i)
                        <div class="border-start border-3 ps-2 mt-2 small">
                            <strong>{{ str_replace('_', ' ', $i->stage) }}</strong> by {{ $i->inspector->name }} ({{ $i->inspector_role_code }}) —
                            <span class="{{ $i->decision === 'rejected' ? 'text-danger' : 'text-success' }}">{{ $i->decision }}</span>
                            · {{ d($i->inspected_at, true) }}
                            @if ($i->distance_from_site_m !== null)· {{ round($i->distance_from_site_m) }} m from site @endif
                            @if ($i->location_override)<span class="badge text-bg-dark">location override</span>@endif
                            @if ($i->comment)<div class="text-muted">“{{ $i->comment }}”</div>@endif
                            @include('reports._evidence-thumbs', ['items' => $i->evidences])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
@endif
