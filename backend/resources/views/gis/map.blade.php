@php($urls = [
    'sections' => route('map.sections'),
    'assets' => route('map.assets'),
    'reports' => route('map.reports'),
    'sectionInfo' => route('map.section-info', ['roadSection' => ':id']),
    'assetBase' => url('assets'),
])
<x-app-layout title="Map" :breadcrumbs="['Map']">
    <x-slot:head>@vite('resources/js/gis/map-dashboard.js')</x-slot:head>
    <x-page-header title="Road network map" subtitle="Click a road to see section, contract, contractor and responsible officers.">
        @can('gis.manage')
            <a href="{{ route('gis.export.all') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Export GeoJSON</a>
        @endcan
        <a href="{{ route('gis.locate') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-crosshair me-1"></i>Locate a point</a>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-3 order-lg-1 order-2">
            <form id="mapFilters" class="card mb-3">
                <div class="card-header">Filters</div>
                <div class="card-body vstack gap-2">
                    <select name="division_id" id="f_division_id" class="form-select form-select-sm" aria-label="Division">
                        <option value="">All divisions</option>
                        @foreach ($divisions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <select name="sub_division_id" data-depends-on="#f_division_id" class="form-select form-select-sm" aria-label="Sub-division">
                        <option value="">All sub-divisions</option>
                        @foreach ($subDivisions as $sub)<option value="{{ $sub->id }}" data-parent="{{ $sub->division_id }}">{{ $sub->name }}</option>@endforeach
                    </select>
                    <select name="contractor_id" class="form-select form-select-sm" aria-label="Contractor">
                        <option value="">All contractors</option>
                        @foreach ($contractors as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <select name="maintenance" class="form-select form-select-sm" aria-label="Maintenance">
                        <option value="">Any maintenance status</option>
                        <option value="active">In maintenance (contractor)</option>
                        <option value="department">No maintenance (department)</option>
                    </select>
                    @foreach (['je_id' => [$jes, 'All JEs'], 'ae_id' => [$aes, 'All AEs'], 'ee_id' => [$ees, 'All EEs']] as $name => [$options, $label])
                        <select name="{{ $name }}" class="form-select form-select-sm" aria-label="{{ $label }}">
                            <option value="">{{ $label }}</option>
                            @foreach ($options as $id => $person)<option value="{{ $id }}">{{ $person }}</option>@endforeach
                        </select>
                    @endforeach
                    <select name="asset_type_id" class="form-select form-select-sm" aria-label="Asset type">
                        <option value="">All asset types</option>
                        @foreach ($assetTypes as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <div class="small fw-semibold border-top pt-2">Damage reports</div>
                    <select name="status_group" class="form-select form-select-sm" aria-label="Report status">
                        <option value="">All open issues</option>
                        <option value="open">Reported / awaiting action</option>
                        <option value="repairing">Repairing / reopened</option>
                        <option value="review">Under review</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select name="severity_id" class="form-select form-select-sm" aria-label="Severity">
                        <option value="">All severities</option>
                        @foreach ($severities as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <div class="d-flex gap-1">
                        <input type="date" name="from" class="form-control form-control-sm" title="Reported from" aria-label="From">
                        <input type="date" name="to" class="form-control form-control-sm" title="Reported to" aria-label="To">
                    </div>
                </div>
            </form>
            <div class="card">
                <div class="card-header">Selected road</div>
                <div class="card-body" id="mapInfo"><div class="text-muted small">Click a road section on the map.</div></div>
            </div>
        </div>
        <div class="col-lg-9 order-lg-2 order-1">
            <div class="card">
                <div id="rcdMap" style="height: calc(100vh - 210px); min-height: 480px"
                     data-config='@json($mapConfig)'
                     data-urls='@json($urls)'></div>
                <div class="card-footer bg-white small text-muted" id="mapStatus">Loading…</div>
            </div>
        </div>
    </div>
</x-app-layout>
