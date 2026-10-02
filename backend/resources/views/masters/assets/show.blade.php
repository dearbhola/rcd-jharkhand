<x-app-layout :title="$asset->code" :breadcrumbs="['Assets' => route('assets.index'), $asset->code]">
    <x-page-header :title="$asset->code.' · '.$asset->name" :subtitle="$asset->type->name">
        <x-test-badge :model="$asset" />
        <x-status-badge :status="$asset->status" />
        @can('asset.update')<a href="{{ route('assets.edit', $asset) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
    </x-page-header>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-3">Road</dt><dd class="col-sm-9"><a href="{{ route('roads.show', $asset->road) }}">{{ $asset->road->code }}</a> — {{ $asset->road->name }}</dd>
                        <dt class="col-sm-3">Section</dt><dd class="col-sm-9">@if ($asset->section)<a href="{{ route('road-sections.show', $asset->section) }}">{{ $asset->section->code }}</a>@else <span class="text-warning">Not inside any section</span> @endif</dd>
                        <dt class="col-sm-3">Chainage</dt><dd class="col-sm-9">{{ km($asset->chainage_m, true) }}{{ $asset->end_chainage_m ? ' – '.km($asset->end_chainage_m, true) : '' }}</dd>
                        <dt class="col-sm-3">Coordinates</dt><dd class="col-sm-9">{{ $asset->latitude !== null ? number_format($asset->latitude, 6).', '.number_format($asset->longitude, 6) : 'Not located (road has no geometry)' }}</dd>
                        <dt class="col-sm-3">External ref.</dt><dd class="col-sm-9">{{ $asset->external_ref ?? '—' }}</dd>
                        <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ d($asset->created_at, true) }} {{ $asset->creator ? 'by '.$asset->creator->name : '' }}</dd>
                        @if ($asset->description)<dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $asset->description }}</dd>@endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Responsible today</div>
                <ul class="list-group list-group-flush">
                    @foreach ($people as $role => $user)
                        <li class="list-group-item d-flex justify-content-between"><span class="fw-semibold">{{ $role }}</span><span>{{ $user?->name ?? '—' }}</span></li>
                    @endforeach
                </ul>
                <div class="card-footer bg-white small text-muted">{{ $hasOverride ? 'Asset-specific mapping applies where set; otherwise the section\'s.' : 'Inherited from the road section.' }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
