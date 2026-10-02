@php($editing = $road->exists)
<x-app-layout :title="$editing ? 'Edit '.$road->code : 'New road'"
              :breadcrumbs="array_filter(['Roads' => route('roads.index'), $road->code => $editing ? route('roads.show', $road) : null, ($editing ? 'Edit' : 'New')])">
    <x-page-header :title="$editing ? 'Edit '.$road->code : 'New road'" />
    <form method="POST" action="{{ $editing ? route('roads.update', $road) : route('roads.store') }}" class="card" novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card-body row g-3">
            <x-form.input name="code" label="Road code" :value="$road->code" required col="col-md-3" placeholder="RCD-021" />
            <x-form.input name="name" label="Road name" :value="$road->name" required col="col-md-6" />
            <x-form.input name="road_number" label="Road number" :value="$road->road_number" col="col-md-3" />
            <x-form.select name="road_category_id" label="Category" :options="$categories" :value="$road->road_category_id" col="col-md-3" />
            <x-form.select name="division_id" label="Primary division" :options="$divisions" :value="$road->division_id" required col="col-md-3" />
            @include('masters.roads._sub-division-select', ['value' => $road->sub_division_id, 'col' => 'col-md-3'])
            <x-form.select name="district_id" label="District" :options="$districts" :value="$road->district_id" col="col-md-3" />
            <x-form.input name="start_location" label="Start location" :value="$road->start_location" />
            <x-form.input name="end_location" label="End location" :value="$road->end_location" />
            <x-form.input name="start_km" label="Start chainage (km)" type="number" step="0.001" min="0" :value="km($road->start_chainage_m)" required col="col-md-3" error-key="start_chainage_m" />
            <x-form.input name="end_km" label="End chainage (km)" type="number" step="0.001" min="0" :value="$road->exists ? km($road->end_chainage_m) : null" required col="col-md-3" error-key="end_chainage_m" />
            <x-form.select name="status" label="Status" :options="['active' => 'Active', 'under_construction' => 'Under construction', 'inactive' => 'Inactive']" :value="$road->status" :placeholder="false" required col="col-md-3" />
            <x-form.input name="external_ref" label="External / official GIS reference" :value="$road->external_ref" col="col-md-3" />
            <x-form.textarea name="description" label="Description" :value="$road->description" />
            <x-form.test-flag :model="$road" />
        </div>
        <div class="card-footer bg-white"><x-form.actions :cancel="$editing ? route('roads.show', $road) : route('roads.index')" /></div>
    </form>
</x-app-layout>
