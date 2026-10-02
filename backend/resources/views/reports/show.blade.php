@php
    $flagLabels = [
        'possible_duplicate' => ['Possible duplicate of a nearby report', 'warning'],
        'location_override' => ['Location picked manually in test mode', 'dark'],
        'responsibility_gap' => ['JE/AE/EE mapping incomplete — routing blocked', 'danger'],
        'delayed_submission' => ['Submitted more than 30 min after capture', 'info'],
    ];
    $evidenceFlags = [
        'exif_gps_mismatch' => 'photo GPS differs from report GPS',
        'exif_time_mismatch' => 'photo time differs from capture time',
        'duplicate_elsewhere' => 'same file used in another report',
        'duration_unverified' => 'video length not verified',
        'video_not_processed' => 'watermark not applied (server video tools unavailable)',
    ];
    $mapData = [
        'lat' => $report->latitude, 'lng' => $report->longitude, 'accuracy' => $report->gps_accuracy_m,
        'color' => $report->severity->color, 'label' => $report->report_no,
        'section' => $report->section?->geojson ? json_decode($report->section->geojson, true) : null,
    ];
@endphp
<x-app-layout :title="$report->report_no" :breadcrumbs="['Reports' => route('reports.index'), $report->report_no]">
    <x-slot:head>@vite(['resources/js/reports/show-map.js', 'resources/js/reports/actions.js'])</x-slot:head>
    <script type="application/json" id="actionConfig">@json($actionConfig)</script>
    <x-page-header :title="$report->report_no" :subtitle="$report->category->name.' · '.$report->assetType->name">
        <x-test-badge :model="$report" />
        <x-severity-badge :severity="$report->severity" />
        <x-report-status :status="$report->status" />
    </x-page-header>

    @foreach ($report->location_flags ?? [] as $flag)
        @isset($flagLabels[$flag])
            <div class="alert alert-{{ $flagLabels[$flag][1] }} py-2 small"><i class="bi bi-flag me-1"></i>{{ $flagLabels[$flag][0] }}</div>
        @endisset
    @endforeach

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">Location</div>
                <div id="reportMap" style="height: 300px" data-config='@json($mapConfig)' data-report='@json($mapData)'></div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-3">Road</dt><dd class="col-sm-9">@can('road.view')<a href="{{ route('roads.show', $report->road) }}">{{ $report->road->code }}</a>@else {{ $report->road->code }} @endcan — {{ $report->road->name }}</dd>
                        <dt class="col-sm-3">Section</dt><dd class="col-sm-3">{{ $report->section->code }}</dd>
                        <dt class="col-sm-3">Chainage</dt><dd class="col-sm-3"><strong>{{ km($report->chainage_m, true) }}</strong></dd>
                        @if ($report->asset)<dt class="col-sm-3">Asset</dt><dd class="col-sm-9">{{ $report->asset->type->name }} · {{ $report->asset->code }} — {{ $report->asset->name }}</dd>@endif
                        <dt class="col-sm-3">GPS</dt><dd class="col-sm-9">{{ number_format($report->latitude, 6) }}, {{ number_format($report->longitude, 6) }} (±{{ round($report->gps_accuracy_m) }} m, {{ round($report->distance_from_road_m) }} m from centre line)</dd>
                        <dt class="col-sm-3">Captured</dt><dd class="col-sm-3">{{ d($report->captured_at_device, true) }}</dd>
                        <dt class="col-sm-3">Received</dt><dd class="col-sm-3">{{ d($report->received_at, true) }}</dd>
                        <dt class="col-sm-3">Reported by</dt><dd class="col-sm-9">{{ $report->reporter->name }} <span class="text-muted">({{ $report->reporter_role_code }})</span></dd>
                        @if ($report->description)<dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $report->description }}</dd>@endif
                    </dl>
                </div>
            </div>

            @include('reports._attempts')
            @foreach ($report->inspections as $i)
                <div class="alert alert-light border small">
                    <strong>{{ $i->stage === 'VALIDATION' ? 'Validation' : $i->stage }}</strong> by {{ $i->inspector->name }} ({{ $i->inspector_role_code }}): {{ $i->decision }}
                    · {{ d($i->inspected_at, true) }} @if ($i->comment)— “{{ $i->comment }}” @endif
                    @include('reports._evidence-thumbs', ['items' => $i->evidences])
                </div>
            @endforeach
            <div class="card mb-3">
                <div class="card-header">Reported evidence <span class="text-muted small fw-normal">({{ $report->evidences->count() }})</span></div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach ($report->evidences as $ev)
                            <div class="col-6 col-md-4">
                                <div class="border rounded p-1 h-100">
                                    <a href="{{ route('evidence.display', $ev) }}" target="_blank" class="d-block text-center bg-light rounded" style="min-height:120px">
                                        @if ($ev->thumbnail_path)
                                            <img src="{{ route('evidence.thumbnail', $ev) }}" class="img-fluid rounded" alt="Evidence {{ $loop->iteration }}" loading="lazy">
                                        @else
                                            <i class="bi {{ $ev->kind === 'video' ? 'bi-camera-video' : 'bi-image' }} fs-1 d-block pt-4 text-muted"></i>
                                            <span class="small text-muted">{{ $ev->processing_status === 'pending' ? 'Processing…' : ucfirst($ev->kind) }}</span>
                                        @endif
                                    </a>
                                    <div class="small text-muted mt-1">{{ ucfirst($ev->kind) }} · {{ d($ev->captured_at, true) }}</div>
                                    @foreach ($ev->flags ?? [] as $f)
                                        <div class="small text-warning"><i class="bi bi-exclamation-circle"></i> {{ $evidenceFlags[$f] ?? $f }}</div>
                                    @endforeach
                                    @can('view-original-evidence', $ev)
                                        <a href="{{ route('evidence.original', $ev) }}" class="small">Original</a>
                                        <span class="small text-muted" title="SHA-256 {{ $ev->sha256 }}">· #{{ substr($ev->sha256, 0, 10) }}</span>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 order-lg-2 order-first">
            @include('reports._actions')
            @include('reports._reassign')
            @if ($responsibility)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between">Responsibility
                        <span class="badge {{ $responsibility->route === 'contractor' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $responsibility->route === 'contractor' ? 'Contractor maintenance' : 'Department' }}</span>
                    </div>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between"><span>Contractor</span><span>{{ $responsibility->contractor?->name ?? '—' }}</span></li>
                        @if ($responsibility->contract)
                            <li class="list-group-item d-flex justify-content-between"><span>Contract</span><span>{{ $responsibility->contract->contract_no }}</span></li>
                        @endif
                        <li class="list-group-item d-flex justify-content-between"><span>JE</span><span>{{ $responsibility->je?->name ?? '— not mapped —' }}</span></li>
                        <li class="list-group-item d-flex justify-content-between"><span>AE</span><span>{{ $responsibility->ae?->name ?? '— not mapped —' }}</span></li>
                        <li class="list-group-item d-flex justify-content-between"><span>EE</span><span>{{ $responsibility->ee?->name ?? '— not mapped —' }}</span></li>
                    </ul>
                    <div class="card-footer bg-white small text-muted">Resolved from GPS on {{ d($responsibility->resolved_at, true) }}. Later mapping changes do not alter this record.</div>
                </div>
            @endif

            @if ($duplicates->isNotEmpty())
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-warning-subtle">Possible duplicates nearby</div>
                    <ul class="list-group list-group-flush small">
                        @foreach ($duplicates as $d)
                            <li class="list-group-item d-flex justify-content-between">
                                <a href="{{ route('reports.show', $d['report']) }}">{{ $d['report']->report_no }}</a>
                                <span class="text-muted">{{ round($d['distance_m']) }} m · {{ $d['report']->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="card-footer bg-white small text-muted">The JE can merge duplicates during validation. Nothing is merged automatically.</div>
                </div>
            @endif

            @include('reports._sla')
            @include('reports._timeline')
        </div>
    </div>
</x-app-layout>
