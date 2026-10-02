<x-app-layout title="Assets" :breadcrumbs="['Masters', 'Assets']">
    <x-page-header title="Assets" subtitle="Bridges, culverts, walls, drains and other road assets">
        @can('asset.create')<a href="{{ route('assets.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New asset</a>@endcan
    </x-page-header>

    <form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
        <div class="col-md-3"><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Asset code or name"></div>
        <div class="col-6 col-md-3">
            <select name="asset_type_id" class="form-select form-select-sm" aria-label="Type">
                <option value="">All types</option>
                @foreach ($types as $id => $name)<option value="{{ $id }}" @selected(($filters['asset_type_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="road_id" class="form-select form-select-sm" aria-label="Road">
                <option value="">All roads</option>
                @foreach ($roads as $id => $name)<option value="{{ $id }}" @selected(($filters['road_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="status" class="form-select form-select-sm" aria-label="Status">
                <option value="">Any status</option>
                @foreach (['active', 'under_repair', 'inactive', 'decommissioned'] as $st)<option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ str_replace('_', ' ', ucfirst($st)) }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill" title="Filter"><i class="bi bi-search"></i></button>
            <a href="{{ route('assets.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Road / section</th><th class="text-end">km</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($assets as $asset)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('assets.show', $asset) }}" class="fw-semibold">{{ $asset->code }}</a> <x-test-badge :model="$asset" /></td>
                        <td>{{ $asset->name }}</td>
                        <td>{{ $asset->type->name }}</td>
                        <td class="small">{{ $asset->road->code }}{{ $asset->section ? ' / '.$asset->section->code : '' }}</td>
                        <td class="text-end">{{ km($asset->chainage_m) }}</td>
                        <td><x-status-badge :status="$asset->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No assets match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($assets->hasPages())<div class="card-footer bg-white">{{ $assets->links() }}</div>@endif
    </div>
</x-app-layout>
