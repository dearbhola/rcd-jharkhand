<x-app-layout title="Roads" :breadcrumbs="['Masters', 'Roads']">
    <x-page-header title="Roads" subtitle="Road master, sections and chainage">
        @can('road.create')
            <a href="{{ route('roads.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New road</a>
        @endcan
    </x-page-header>

    <form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
        <div class="col-md-3"><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Code, name or number"></div>
        <div class="col-6 col-md-2">
            <select name="division_id" id="f_division_id" class="form-select form-select-sm" aria-label="Division">
                <option value="">All divisions</option>
                @foreach ($divisions as $id => $name)<option value="{{ $id }}" @selected(($filters['division_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="sub_division_id" data-depends-on="#f_division_id" class="form-select form-select-sm" aria-label="Sub-division">
                <option value="">All sub-divisions</option>
                @foreach ($subDivisions as $sub)<option value="{{ $sub->id }}" data-parent="{{ $sub->division_id }}" @selected(($filters['sub_division_id'] ?? '') == $sub->id)>{{ $sub->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="road_category_id" class="form-select form-select-sm" aria-label="Category">
                <option value="">All categories</option>
                @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected(($filters['road_category_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="status" class="form-select form-select-sm" aria-label="Status">
                <option value="">Any status</option>
                @foreach (['active' => 'Active', 'under_construction' => 'Under construction', 'inactive' => 'Inactive'] as $v => $l)
                    <option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill" title="Filter"><i class="bi bi-search"></i></button>
            <a href="{{ route('roads.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Division</th><th class="text-end">Chainage (km)</th><th class="text-end">Sections</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($roads as $road)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('roads.show', $road) }}" class="fw-semibold">{{ $road->code }}</a> <x-test-badge :model="$road" /></td>
                        <td>{{ $road->name }}<div class="small text-muted">{{ $road->start_location }} → {{ $road->end_location }}</div></td>
                        <td>{{ $road->category?->code }}</td>
                        <td class="small">{{ $road->division->code }}{{ $road->subDivision ? ' / '.$road->subDivision->code : '' }}</td>
                        <td class="text-end text-nowrap">{{ km($road->start_chainage_m) }} – {{ km($road->end_chainage_m) }}</td>
                        <td class="text-end">{{ $road->sections_count }}</td>
                        <td><x-status-badge :status="$road->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No roads match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($roads->hasPages())<div class="card-footer bg-white">{{ $roads->links() }}</div>@endif
    </div>
</x-app-layout>
