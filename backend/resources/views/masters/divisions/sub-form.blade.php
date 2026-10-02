@php($editing = $subDivision->exists)
<x-app-layout :title="$editing ? 'Edit sub-division' : 'New sub-division'" :breadcrumbs="['Divisions' => route('divisions.index'), $editing ? 'Edit sub-division' : 'New sub-division']">
    <x-page-header :title="$editing ? 'Edit sub-division' : 'New sub-division'" />
    <form method="POST" action="{{ $editing ? route('sub-divisions.update', $subDivision) : route('sub-divisions.store') }}" class="card" novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card-body row g-3">
            <x-form.select name="division_id" label="Division" :options="$divisions" :value="$subDivision->division_id" required />
            <x-form.input name="code" label="Code" :value="$subDivision->code" required col="col-md-2" />
            <x-form.input name="name" label="Name" :value="$subDivision->name" required col="col-md-4" />
            <x-form.select name="district_id" label="District" :options="$districts" :value="$subDivision->district_id" />
            <x-form.select name="is_active" label="Status" :options="[1 => 'Active', 0 => 'Inactive']" :value="(int) $subDivision->is_active" :placeholder="false" required />
            <x-form.test-flag :model="$subDivision" />
        </div>
        <div class="card-footer bg-white"><x-form.actions :cancel="route('divisions.index')" /></div>
    </form>
</x-app-layout>
