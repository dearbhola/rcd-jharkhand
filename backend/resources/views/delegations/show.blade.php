<x-app-layout title="Delegation" :breadcrumbs="['Delegations' => route('delegations.index'), $delegation->primaryUser->name]">
    <x-page-header :title="$delegation->primaryUser->name.' → '.$delegation->delegateUser->name" :subtitle="$delegation->role_code->value.' duties · '.$delegation->reason">
        <x-test-badge :model="$delegation" />
        @include('delegations._badge', ['status' => $delegation->status])
    </x-page-header>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5">From</dt><dd class="col-7">{{ d($delegation->starts_at, true) }}</dd>
                        <dt class="col-5">Until</dt><dd class="col-7">{{ d($delegation->ends_at, true) }}</dd>
                        <dt class="col-5">Existing tasks</dt><dd class="col-7">{{ ['all_pending' => 'All moved', 'new_only' => 'Stay with officer', 'selective' => 'Chosen by supervisor'][$delegation->transfer_mode] }}</dd>
                        <dt class="col-5">On end</dt><dd class="col-7">{{ $delegation->return_on_end ? 'Open tasks return' : 'Tasks stay with stand-in' }}</dd>
                        @if ($delegation->activated_at)<dt class="col-5">Activated</dt><dd class="col-7">{{ d($delegation->activated_at, true) }}</dd>@endif
                        @if ($delegation->ended_at)<dt class="col-5">Ended</dt><dd class="col-7">{{ d($delegation->ended_at, true) }}</dd>@endif
                    </dl>
                </div>
            </div>
            @can('delegation.manage')
                @if (in_array($delegation->status, ['scheduled', 'active'], true))
                    <form method="POST" action="{{ route('delegations.end', $delegation) }}" class="card" data-confirm="{{ $delegation->status === 'active' ? 'End this delegation now?' : 'Cancel this delegation?' }}">
                        @csrf
                        <div class="card-body">
                            <label class="form-label required small">Reason</label>
                            <input name="reason" class="form-control form-control-sm mb-2" required minlength="5" placeholder="e.g. Officer rejoined early">
                            <button class="btn btn-sm btn-warning w-100">{{ $delegation->status === 'active' ? 'End delegation now' : 'Cancel delegation' }}</button>
                        </div>
                    </form>
                @endif
            @endcan
        </div>
        <div class="col-lg-8">
            @if ($delegation->status === 'active' && $pending->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header">Open tasks still with {{ $delegation->primaryUser->name }}</div>
                    @can('delegation.manage')
                        <form method="POST" action="{{ route('delegations.transfer', $delegation) }}">
                            @csrf
                            <div class="list-group list-group-flush">
                                @foreach ($pending as $a)
                                    <label class="list-group-item d-flex gap-2">
                                        <input class="form-check-input" type="checkbox" name="assignments[]" value="{{ $a->id }}" data-group="pending">
                                        <span><strong>{{ $a->instance->report->report_no }}</strong> · {{ $a->step->name }} <span class="text-muted small">since {{ d($a->assigned_at, true) }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="card-footer bg-white d-flex gap-3 align-items-center">
                                <label class="small"><input type="checkbox" class="form-check-input" data-check-all="pending"> All</label>
                                <button class="btn btn-sm btn-primary">Transfer selected to {{ $delegation->delegateUser->name }}</button>
                            </div>
                        </form>
                    @endcan
                </div>
            @endif
            <div class="card mb-3">
                <div class="card-header">Tasks handled through this delegation</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Report</th><th>Step</th><th>Holder</th><th>Assigned</th><th>Ended</th></tr></thead>
                        <tbody>
                        @forelse ($transferred as $a)
                            <tr>
                                <td><a href="{{ route('reports.show', $a->instance->report_id) }}">{{ $a->instance->report?->report_no }}</a></td>
                                <td>{{ $a->step->name }}</td>
                                <td>{{ $a->user->name }}</td>
                                <td class="small">{{ d($a->assigned_at, true) }}</td>
                                <td class="small">{{ $a->ended_at ? d($a->ended_at, true).' ('.$a->end_reason.')' : 'open' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center">None yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header">History</div>
                <ul class="list-group list-group-flush small">
                    @foreach ($history as $h)
                        <li class="list-group-item"><span class="fw-semibold">{{ $h->action }}</span> · {{ $h->user_name }} · {{ d($h->created_at, true) }}
                            @if ($h->comment) — “{{ $h->comment }}” @endif
                            @if ($h->new_values) <span class="text-muted">{{ json_encode($h->new_values) }}</span>@endif</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
