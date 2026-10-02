@php($editing = $asset->exists)
<x-app-layout :title="$editing ? 'Edit '.$asset->code : 'New asset'" :breadcrumbs="['Assets' => route('assets.index'), $editing ? 'Edit' : 'New']">
    <x-page-header :title="$editing ? 'Edit '.$asset->code : 'New asset'" />
    <form method="POST" action="{{ $editing ? route('assets.update', $asset) : route('assets.store') }}" class="card" novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card-body row g-3">
            <x-form.input name="code" label="Asset code" :value="$asset->code" required col="col-md-3" />
            <x-form.select name="asset_type_id" label="Asset type" :options="$types" :value="$asset->asset_type_id" required col="col-md-3" />
            <x-form.input name="name" label="Name" :value="$asset->name" required />
            <x-form.select name="road_id" label="Road" :options="$roads" :value="$asset->road_id" required />
            <x-form.input name="chainage_km" label="Chainage (km)" type="number" step="0.001" :value="$asset->chainage_m !== null ? km($asset->chainage_m) : null" required col="col-md-3" error-key="chainage_m" />
            <x-form.input name="end_chainage_km" label="End chainage (km)" type="number" step="0.001" :value="$asset->end_chainage_m !== null ? km($asset->end_chainage_m) : null" col="col-md-3" error-key="end_chainage_m" help="Linear assets only (wall, drain)." />
            <x-form.input name="latitude" label="Latitude" type="number" step="0.0000001" :value="$asset->latitude" col="col-md-3" />
            <x-form.input name="longitude" label="Longitude" type="number" step="0.0000001" :value="$asset->longitude" col="col-md-3" />
            <div class="col-md-6 form-text align-self-end">Leave coordinates empty to place the asset on the road geometry at its chainage. Enter surveyed coordinates to override.</div>
            <x-form.select name="status" label="Status" :options="['active' => 'Active', 'under_repair' => 'Under repair', 'inactive' => 'Inactive', 'decommissioned' => 'Decommissioned']" :value="$asset->status" :placeholder="false" required col="col-md-3" />
            <x-form.input name="external_ref" label="External reference" :value="$asset->external_ref" col="col-md-3" />
            <x-form.textarea name="description" label="Description" :value="$asset->description" />
            <x-form.test-flag :model="$asset" />
        </div>
        <div class="card-footer bg-white"><x-form.actions :cancel="$editing ? route('assets.show', $asset) : route('assets.index')" /></div>
    </form>
</x-app-layout>
