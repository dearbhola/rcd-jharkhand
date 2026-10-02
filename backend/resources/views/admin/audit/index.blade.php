<x-app-layout title="Audit log" :breadcrumbs="['Administration', 'Audit log']">
    <x-page-header title="Audit log" subtitle="Append-only record of important actions" />

    <form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
        <div class="col-6 col-md-2"><input name="action" value="{{ $filters['action'] ?? '' }}" class="form-control form-control-sm" placeholder="Action prefix"></div>
        <div class="col-6 col-md-2"><input name="user" value="{{ $filters['user'] ?? '' }}" class="form-control form-control-sm" placeholder="User"></div>
        <div class="col-6 col-md-2">
            <select name="entity" class="form-select form-select-sm">
                <option value="">Any entity</option>
                @foreach ($entities as $entity)
                    <option value="{{ $entity }}" @selected(($filters['entity'] ?? '') === $entity)>{{ $entity }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-1"><input name="entity_id" value="{{ $filters['entity_id'] ?? '' }}" class="form-control form-control-sm" placeholder="ID" inputmode="numeric"></div>
        <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm" title="From"></div>
        <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm" title="To"></div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill" title="Filter"><i class="bi bi-search"></i></button>
            <a href="{{ route('admin.audit.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th><th></th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at->format('d-M-Y H:i:s') }}</td>
                        <td>{{ $log->user_name }} <span class="text-muted">{{ $log->role_code }}</span></td>
                        <td><code>{{ $log->action }}</code> @if ($log->is_test)<span class="badge badge-test">TEST</span>@endif</td>
                        <td>{{ $log->auditable_type ? $log->auditable_type.' #'.$log->auditable_id : '—' }}</td>
                        <td class="text-muted">{{ $log->ip_address }}</td>
                        <td class="text-end"><a href="{{ route('admin.audit.show', $log) }}">Details</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No audit entries match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer bg-white">{{ $logs->links() }}</div>
        @endif
    </div>
</x-app-layout>
