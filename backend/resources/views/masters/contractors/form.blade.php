@php($editing = $contractor->exists)
<x-app-layout :title="$editing ? 'Edit contractor' : 'New contractor'" :breadcrumbs="['Contractors' => route('contractors.index'), $editing ? 'Edit' : 'New']">
    <x-page-header :title="$editing ? 'Edit '.$contractor->name : 'New contractor'" />
    <form method="POST" action="{{ $editing ? route('contractors.update', $contractor) : route('contractors.store') }}" class="card" novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card-body row g-3">
            <x-form.input name="code" label="Code" :value="$contractor->code" required col="col-md-3" />
            <x-form.input name="name" label="Firm name" :value="$contractor->name" required col="col-md-9" />
            <x-form.input name="registration_no" label="Registration no." :value="$contractor->registration_no" col="col-md-4" />
            <x-form.input name="pan" label="PAN" :value="$contractor->pan" col="col-md-4" maxlength="10" />
            <x-form.input name="gstin" label="GSTIN" :value="$contractor->gstin" col="col-md-4" maxlength="15" />
            <x-form.input name="contact_person" label="Contact person" :value="$contractor->contact_person" col="col-md-4" />
            <x-form.input name="mobile" label="Mobile" :value="$contractor->mobile" col="col-md-4" inputmode="numeric" maxlength="10" />
            <x-form.input name="email" label="Email" type="email" :value="$contractor->email" col="col-md-4" />
            <x-form.input name="address" label="Address" :value="$contractor->address" col="col-md-8" />
            <x-form.select name="status" label="Status" :options="['active' => 'Active', 'suspended' => 'Suspended', 'blacklisted' => 'Blacklisted', 'inactive' => 'Inactive']" :value="$contractor->status" :placeholder="false" required col="col-md-4" />
            <x-form.test-flag :model="$contractor" />
        </div>
        <div class="card-footer bg-white"><x-form.actions :cancel="$editing ? route('contractors.show', $contractor) : route('contractors.index')" /></div>
    </form>
</x-app-layout>
