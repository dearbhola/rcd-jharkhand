@php($modes = ['all_pending' => 'All pending tasks move', 'new_only' => 'Only new tasks', 'selective' => 'Selected tasks'])
<x-app-layout title="Delegations" :breadcrumbs="['Delegations']">
    <x-page-header title="Leave & delegation" subtitle="Task routing while an officer is unavailable. The permanent road mapping is not changed.">
        @can('delegation.manage')<a href="{{ route('delegations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New delegation</a>@endcan
    </x-page-header>
    <form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm" aria-label="Status">
                <option value="">Any status</option>
                @foreach (['active', 'scheduled', 'ended', 'cancelled'] as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Officer away</th><th>Role</th><th>Stand-in</th><th>Period</th><th>Existing tasks</th><th class="text-end">Moved</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($delegations as $d)
                    <tr>
                        <td><a href="{{ route('delegations.show', $d) }}" class="fw-semibold">{{ $d->primaryUser->name }}</a> <x-test-badge :model="$d" /><div class="small text-muted">{{ $d->reason }}</div></td>
                        <td>{{ $d->role_code->value }}</td>
                        <td>{{ $d->delegateUser->name }}</td>
                        <td class="small text-nowrap">{{ d($d->starts_at, true) }}<br>→ {{ d($d->ends_at, true) }}</td>
                        <td class="small">{{ $modes[$d->transfer_mode] ?? $d->transfer_mode }}</td>
                        <td class="text-end">{{ $d->transferred_count }}</td>
                        <td>@include('delegations._badge', ['status' => $d->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No delegations.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($delegations->hasPages())<div class="card-footer bg-white">{{ $delegations->links() }}</div>@endif
    </div>
</x-app-layout>
