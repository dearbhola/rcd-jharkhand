<x-app-layout title="Contractors" :breadcrumbs="['Masters', 'Contractors']">
    <x-page-header title="Contractors">
        @can('contractor.create')<a href="{{ route('contractors.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New contractor</a>@endcan
    </x-page-header>
    <form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
        <div class="col-md-6"><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Name, code or registration number"></div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm" aria-label="Status">
                <option value="">Any status</option>
                @foreach (['active', 'suspended', 'blacklisted', 'inactive'] as $st)<option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-search"></i> Filter</button>
            <a href="{{ route('contractors.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Contractor</th><th>Registration</th><th>Contact</th><th class="text-end">Contracts</th><th class="text-end">In maintenance</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($contractors as $c)
                    <tr>
                        <td><a href="{{ route('contractors.show', $c) }}" class="fw-semibold">{{ $c->name }}</a> <x-test-badge :model="$c" /><div class="small text-muted">{{ $c->code }}</div></td>
                        <td class="small">{{ $c->registration_no ?? '—' }}</td>
                        <td class="small">{{ $c->contact_person }}<div class="text-muted">{{ $c->mobile }}</div></td>
                        <td class="text-end">{{ $c->contracts_count }}</td>
                        <td class="text-end">{{ $c->maintenance_contracts_count }}</td>
                        <td><x-status-badge :status="$c->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No contractors match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($contractors->hasPages())<div class="card-footer bg-white">{{ $contractors->links() }}</div>@endif
    </div>
</x-app-layout>
