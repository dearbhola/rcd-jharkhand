<x-app-layout :title="$road->code" :breadcrumbs="['Roads' => route('roads.index'), $road->code]">
    <x-page-header :title="$road->code.' · '.$road->name" :subtitle="($road->start_location ?? '?').' → '.($road->end_location ?? '?')">
        <x-test-badge :model="$road" />
        <x-status-badge :status="$road->status" />
        @can('gis.manage')
            <a href="{{ route('gis.geometry.edit', $road) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bezier me-1"></i>{{ $road->currentGeometry ? 'Edit geometry' : 'Draw geometry' }}</a>
        @endcan
        @can('road.update')
            <a href="{{ route('roads.edit', $road) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endcan
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-3">Category</dt><dd class="col-sm-3">{{ $road->category?->name ?? '—' }}</dd>
                        <dt class="col-sm-3">Road number</dt><dd class="col-sm-3">{{ $road->road_number ?? '—' }}</dd>
                        <dt class="col-sm-3">Division</dt><dd class="col-sm-3">{{ $road->division->name }}</dd>
                        <dt class="col-sm-3">Sub-division</dt><dd class="col-sm-3">{{ $road->subDivision?->name ?? '—' }}</dd>
                        <dt class="col-sm-3">District</dt><dd class="col-sm-3">{{ $road->district?->name ?? '—' }}</dd>
                        <dt class="col-sm-3">Chainage</dt><dd class="col-sm-3">{{ km($road->start_chainage_m) }} – {{ km($road->end_chainage_m, true) }}</dd>
                        <dt class="col-sm-3">Length</dt><dd class="col-sm-3">{{ km($road->length_m, true) }} {!! $road->currentGeometry ? '<span class="text-muted">(measured)</span>' : '<span class="text-muted">(declared)</span>' !!}</dd>
                        <dt class="col-sm-3">External ref.</dt><dd class="col-sm-3">{{ $road->external_ref ?? '—' }}</dd>
                        <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ d($road->created_at, true) }} {{ $road->creator ? 'by '.$road->creator->name : '' }}
                            @if ($road->updater) · updated {{ d($road->updated_at, true) }} by {{ $road->updater->name }} @endif</dd>
                        @if ($road->description)<dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $road->description }}</dd>@endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Geometry</div>
                <div class="card-body small">
                    @if ($road->currentGeometry)
                        <div><i class="bi bi-check-circle text-success"></i> Version {{ $road->currentGeometry->version }} ({{ $road->currentGeometry->source }}), {{ km($road->currentGeometry->length_m, true) }}</div>
                        <ul class="list-unstyled text-muted mt-2 mb-0">
                            @foreach ($geometryVersions as $g)
                                <li>v{{ $g->version }} · {{ d($g->created_at, true) }} · {{ $g->creator?->name ?? 'system' }}{{ $g->is_current ? ' · current' : '' }}</li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-warning"><i class="bi bi-exclamation-triangle"></i> No geometry yet. Field reports cannot be located on this road until it is drawn or imported (GIS tools).</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Sections &amp; current responsibility <span class="text-muted small fw-normal">as of {{ d(now()) }}</span></span>
            @can('road_section.manage')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addSection"><i class="bi bi-plus"></i> Add section</button>
            @endcan
        </div>

        @can('road_section.manage')
            <div id="addSection" @class(['collapse', 'show' => $errors->hasAny(['code', 'start_chainage_m', 'end_chainage_m', 'division_id'])])>
                <form method="POST" action="{{ route('road-sections.store', $road) }}" class="card-body border-bottom row g-2 bg-light" novalidate>
                    @csrf
                    <x-form.input name="code" label="Code" col="col-md-2" required placeholder="S04" />
                    <x-form.input name="name" label="Name" col="col-md-3" />
                    <x-form.input name="start_km" label="Start km" type="number" step="0.001" col="col-md-2" required error-key="start_chainage_m" />
                    <x-form.input name="end_km" label="End km" type="number" step="0.001" col="col-md-2" required error-key="end_chainage_m" />
                    <x-form.select name="division_id" label="Division" :options="$divisions" :value="$road->division_id" col="col-md-3" required />
                    @include('masters.roads._sub-division-select', ['value' => $road->sub_division_id, 'col' => 'col-md-3'])
                    <input type="hidden" name="status" value="active">
                    <div class="col-md-9 d-flex align-items-end"><button class="btn btn-sm btn-primary"><i class="bi bi-check2"></i> Add section</button></div>
                </form>
            </div>
        @endcan

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Section</th><th class="text-end">km</th><th>Contractor</th><th>Maintenance</th><th>JE</th><th>AE</th><th>EE</th><th></th></tr></thead>
                <tbody>
                @forelse ($sections as $row)
                    @php($s = $row['section'])
                    <tr>
                        <td><a href="{{ route('road-sections.show', $s) }}" class="fw-semibold">{{ $s->code }}</a>
                            @if ($s->status !== 'active')<span class="badge text-bg-secondary">{{ $s->status }}</span>@endif
                            @unless ($s->geojson)<i class="bi bi-geo text-warning" title="No geometry"></i>@endunless
                        </td>
                        <td class="text-end text-nowrap">{{ km($s->start_chainage_m) }} – {{ km($s->end_chainage_m) }}</td>
                        <td class="small">
                            @forelse ($row['mappings'] as $m)
                                <div><a href="{{ route('contracts.show', $m->contract_id) }}">{{ $m->contract->contractor->name }}</a>
                                    @if ($m->start_chainage_m > $s->start_chainage_m || $m->end_chainage_m < $s->end_chainage_m)
                                        <span class="text-muted">(km {{ km($m->start_chainage_m) }}–{{ km($m->end_chainage_m) }})</span>
                                    @endif
                                </div>
                            @empty
                                <span class="text-muted">No contract</span>
                            @endforelse
                        </td>
                        <td>{!! $row['maintenance_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Department</span>' !!}</td>
                        @foreach (['JE', 'AE', 'EE'] as $role)
                            <td class="small">{{ $row['people'][$role]?->name ?? '—' }}</td>
                        @endforeach
                        <td class="text-end text-nowrap">
                            @can('road_section.manage')
                                <a href="{{ route('road-sections.edit', $s) }}" class="btn btn-sm btn-link px-1">Edit</a>
                                <button class="btn btn-sm btn-link px-1" data-bs-toggle="modal" data-bs-target="#split{{ $s->id }}">Split</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-3">No sections yet. Every road needs at least one section to receive reports.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('road_section.manage')
        @foreach ($sections as $row)
            @php($s = $row['section'])
            <div class="modal fade" id="split{{ $s->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('road-sections.split', $s) }}" class="modal-content">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">Split section {{ $s->code }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body row g-2">
                            <p class="small text-muted mb-1">{{ $s->code }} covers km {{ km($s->start_chainage_m) }} – {{ km($s->end_chainage_m) }}. It will end at the split point; a new section takes the remainder and inherits the current JE/AE/EE mapping.</p>
                            <div class="col-4"><label class="form-label required small">Split at km</label><input name="split_km" type="number" step="0.001" class="form-control form-control-sm" required></div>
                            <div class="col-3"><label class="form-label required small">New code</label><input name="new_code" class="form-control form-control-sm" required></div>
                            <div class="col-5"><label class="form-label small">New name</label><input name="new_name" class="form-control form-control-sm"></div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-primary btn-sm">Split section</button></div>
                    </form>
                </div>
            </div>
        @endforeach
    @endcan

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Assets
                    @can('asset.create')<a href="{{ route('assets.create', ['road_id' => $road->id]) }}" class="btn btn-sm btn-link p-0">Add asset</a>@endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Code</th><th>Type</th><th class="text-end">km</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($assets as $asset)
                            <tr>
                                <td><a href="{{ route('assets.show', $asset) }}">{{ $asset->code }}</a></td>
                                <td>{{ $asset->type->name }}</td>
                                <td class="text-end">{{ km($asset->chainage_m) }}</td>
                                <td><x-status-badge :status="$asset->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center">No assets recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Contract history</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Contract</th><th>Contractor</th><th class="text-end">km</th><th>Effective</th></tr></thead>
                        <tbody>
                        @forelse ($contractHistory as $m)
                            <tr @class(['text-muted' => $m->effective_to && $m->effective_to->isPast()])>
                                <td><a href="{{ route('contracts.show', $m->contract_id) }}">{{ $m->contract->contract_no }}</a></td>
                                <td>{{ $m->contract->contractor->name }}</td>
                                <td class="text-end text-nowrap">{{ km($m->start_chainage_m) }}–{{ km($m->end_chainage_m) }}</td>
                                <td class="small">{{ d($m->effective_from) }} →<br>{{ $m->effective_to ? d($m->effective_to) : 'open' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center">No contracts mapped.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
