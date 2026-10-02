<x-app-layout title="Locate a point" :breadcrumbs="['Map' => route('map.index'), 'Locate']">
    <x-slot:head>@vite('resources/js/gis/locate.js')</x-slot:head>
    <x-page-header title="Locate a point" subtitle="Shows exactly how the system resolves a GPS position: road, section, chainage, contract, contractor, JE/AE/EE and workflow route." />
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card"><div id="locateMap" style="height: calc(100vh - 220px); min-height: 460px" data-config='@json($mapConfig)' data-resolve-url="{{ route('gis.resolve') }}"></div></div>
        </div>
        <div class="col-lg-4">
            <form id="locateForm" class="card mb-3">
                <div class="card-body row g-2">
                    <div class="col-6"><label class="form-label small">Latitude</label><input name="lat" class="form-control form-control-sm" inputmode="decimal"></div>
                    <div class="col-6"><label class="form-label small">Longitude</label><input name="lng" class="form-control form-control-sm" inputmode="decimal"></div>
                    <div class="col-6"><label class="form-label small">Radius (m)</label><input name="radius" type="number" min="5" max="5000" value="{{ $defaultRadius }}" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small">As of date</label><input name="date" type="date" value="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
                    <div class="col-6"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Resolve</button></div>
                    <div class="col-6"><button type="button" id="myLocation" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-crosshair"></i> My location</button></div>
                    <div class="col-12 form-text">Or click anywhere on the map. Default radius = reporting radius setting.</div>
                </div>
            </form>
            <div class="card"><div class="card-body" id="locateResult"><div class="text-muted small">No point selected.</div></div></div>
        </div>
    </div>
</x-app-layout>
