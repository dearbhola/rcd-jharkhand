@php($editing = $division->exists)
<x-app-layout :title="$editing ? 'Edit division' : 'New division'" :breadcrumbs="['Divisions' => route('divisions.index'), $editing ? 'Edit' : 'New']">
    <x-page-header :title="$editing ? 'Edit division' : 'New division'" />
    <form method="POST" action="{{ $editing ? route('divisions.update', $division) : route('divisions.store') }}" class="card" novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card-body row g-3">
            <x-form.input name="code" label="Code" :value="$division->code" required col="col-md-3" />
            <x-form.input name="name" label="Name" :value="$division->name" required col="col-md-9" />
            <x-form.select name="district_id" label="District" :options="$districts" :value="$division->district_id" />
            <x-form.select name="is_active" label="Status" :options="[1 => 'Active', 0 => 'Inactive']" :value="(int) $division->is_active" :placeholder="false" required />
            <x-form.input name="address" label="Office address" :value="$division->address" col="col-12" />
            <x-form.test-flag :model="$division" />
        </div>
        <div class="card-footer bg-white"><x-form.actions :cancel="route('divisions.index')" /></div>
    </form>
</x-app-layout>
