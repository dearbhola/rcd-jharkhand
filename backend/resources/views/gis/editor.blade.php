<x-app-layout :title="'Geometry: '.$road->code" :breadcrumbs="['Roads' => route('roads.index'), $road->code => route('roads.show', $road), 'Geometry']">
    <x-slot:head>@vite('resources/js/gis/geometry-editor.js')</x-slot:head>
    <x-page-header :title="'Geometry · '.$road->code" :subtitle="$road->name">
        <a href="{{ route('gis.export.road', $road) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Export GeoJSON</a>
        <a href="{{ route('roads.show', $road) }}" class="btn btn-outline-primary btn-sm">Back to road</a>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-9">
            <div class="card"><div id="editorMap" style="height: calc(100vh - 220px); min-height: 480px" data-config='@json($mapConfig)'></div></div>
        </div>
        <div class="col-lg-3">
            <div id="editorMessage" class="alert alert-info small py-2">
                {{ $road->currentGeometry ? 'Version '.$road->currentGeometry->version.' loaded.' : 'No geometry yet. Draw the centre line with the line tool, or import a file.' }}
                Draw in the direction of increasing chainage.
            </div>
            <div class="card mb-3">
                <div class="card-header">Centre line</div>
                <div class="card-body vstack gap-2">
                    <dl class="row small mb-0">
                        <dt class="col-6">Drawn length</dt><dd class="col-6" id="statLength">—</dd>
                        <dt class="col-6">Declared chainage</dt><dd class="col-6" id="statDeclared">—</dd>
                        <dt class="col-6">Vertices</dt><dd class="col-6" id="statVertices">—</dd>
                    </dl>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="editBtn"><i class="bi bi-pencil"></i> Edit vertices</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="reverseBtn"><i class="bi bi-arrow-left-right"></i> Reverse direction</button>
                    <label class="btn btn-sm btn-outline-secondary mb-0"><i class="bi bi-upload"></i> Import GeoJSON / KML / GPX
                        <input type="file" id="importFile" accept=".geojson,.json,.kml,.gpx" hidden></label>
                    <input id="remarks" class="form-control form-control-sm" placeholder="Remarks (e.g. surveyed 02-Oct)" maxlength="255">
                    <button type="button" class="btn btn-primary" id="saveBtn" disabled><i class="bi bi-check2"></i> Save new version</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Km-stone markers</div>
                <div class="card-body small">
                    <p class="text-muted mb-2">Markers calibrate chainage so that km on the ground matches km in the system. Sections are re-cut automatically.</p>
                    @if ($road->currentGeometry)
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="markerBtn"><i class="bi bi-geo"></i> Add marker (click map)</button>
                    @else
                        <div class="text-muted">Save a geometry first.</div>
                    @endif
                    <ul class="list-unstyled mt-2 mb-0">
                        @foreach ($road->chainageMarkers as $marker)
                            <li>km {{ km($marker->chainage_m) }} {{ $marker->label ? '· '.$marker->label : '' }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script type="application/json" id="editorData">@json($payload)</script>
</x-app-layout>
