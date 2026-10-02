@php($stageNames = \App\Http\Controllers\Admin\SlaController::STAGES)
@if ($slaHistory->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header">SLA</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 small align-middle">
                <thead><tr><th>Stage</th><th>Due</th><th>Result</th></tr></thead>
                <tbody>
                @foreach ($slaHistory as $s)
                    @php($late = $s->breached_at || (! $s->completed_at && $s->due_at->isPast()))
                    <tr>
                        <td>{{ $stageNames[$s->stage] ?? $s->stage }}<div class="text-muted">{{ $s->hours + 0 }} h · {{ $s->responsibleUser?->name }}</div></td>
                        <td class="text-nowrap">{{ d($s->due_at, true) }}</td>
                        <td>
                            @if ($s->completed_at)
                                <span class="badge {{ $s->breached_at ? 'text-bg-danger' : 'text-bg-success' }}">{{ $s->breached_at ? 'Late' : 'On time' }}</span>
                                <span class="text-muted">{{ $s->started_at->diffForHumans($s->completed_at, true) }}</span>
                            @elseif ($late)
                                <span class="badge text-bg-danger">Overdue {{ $s->due_at->diffForHumans(null, true) }}</span>
                            @else
                                <span class="badge text-bg-info">Running · {{ $s->due_at->diffForHumans() }}</span>
                            @endif
                            @foreach ($s->escalations as $e)
                                <div class="text-muted"><i class="bi bi-bell"></i> {{ $e->rule->action === 'remind' ? 'Reminder' : 'Escalated'.($e->rule->notify_role_code ? ' to '.$e->rule->notify_role_code : '') }} · {{ d($e->fired_at, true) }}</div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
