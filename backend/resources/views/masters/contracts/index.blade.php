<x-app-layout title="Contracts" :breadcrumbs="['Masters', 'Contracts']">
    <x-page-header title="Contracts" subtitle="Contractor responsibility is decided by the maintenance period">
        @can('contract.create')<a href="{{ route('contracts.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New contract</a>@endcan
    </x-page-header>
    <form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
        <div class="col-md-3"><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Contract / agreement no."></div>
        <div class="col-6 col-md-3">
            <select name="contractor_id" class="form-select form-select-sm" aria-label="Contractor">
                <option value="">All contractors</option>
                @foreach ($contractors as $id => $name)<option value="{{ $id }}" @selected(($filters['contractor_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="division_id" class="form-select form-select-sm" aria-label="Division">
                <option value="">All divisions</option>
                @foreach ($divisions as $id => $name)<option value="{{ $id }}" @selected(($filters['division_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="maintenance" class="form-select form-select-sm" aria-label="Maintenance">
                <option value="">Any maintenance</option>
                @foreach (['active' => 'In maintenance today', 'upcoming' => 'Maintenance not started', 'expired' => 'Maintenance expired'] as $v => $l)<option value="{{ $v }}" @selected(($filters['maintenance'] ?? '') === $v)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-1">
            <select name="status" class="form-select form-select-sm" aria-label="Status">
                <option value="">Status</option>
                @foreach (['draft', 'active', 'completed', 'terminated'] as $st)<option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill" title="Filter"><i class="bi bi-search"></i></button>
            <a href="{{ route('contracts.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Contract</th><th>Contractor</th><th>Div.</th><th>Maintenance period</th><th class="text-end">Coverage</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($contracts as $c)
                    <tr>
                        <td><a href="{{ route('contracts.show', $c) }}" class="fw-semibold">{{ $c->contract_no }}</a> <x-test-badge :model="$c" /><div class="small text-muted">{{ $c->name }}</div></td>
                        <td>{{ $c->contractor->name }}</td>
                        <td>{{ $c->division?->code }}</td>
                        <td class="small text-nowrap">{{ d($c->maintenance_start_date) }} → {{ d($c->maintenance_end_date) }}
                            @if ($c->isMaintenanceActiveOn(now()))<span class="badge text-bg-success">active</span>@endif</td>
                        <td class="text-end">{{ $c->road_sections_count }}</td>
                        <td><x-status-badge :status="$c->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No contracts match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($contracts->hasPages())<div class="card-footer bg-white">{{ $contracts->links() }}</div>@endif
    </div>
</x-app-layout>
