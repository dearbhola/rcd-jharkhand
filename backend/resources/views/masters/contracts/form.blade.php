@php($editing = $contract->exists)
<x-app-layout :title="$editing ? 'Edit '.$contract->contract_no : 'New contract'" :breadcrumbs="['Contracts' => route('contracts.index'), $editing ? 'Edit' : 'New']">
    <x-page-header :title="$editing ? 'Edit '.$contract->contract_no : 'New contract'" />
    <form method="POST" action="{{ $editing ? route('contracts.update', $contract) : route('contracts.store') }}" class="card" novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card-body row g-3">
            <x-form.input name="contract_no" label="Contract number" :value="$contract->contract_no" required col="col-md-4" />
            <x-form.input name="name" label="Name of work" :value="$contract->name" col="col-md-8" />
            <x-form.select name="contractor_id" label="Contractor" :options="$contractors" :value="$contract->contractor_id" required />
            <x-form.select name="division_id" label="Division" :options="$divisions" :value="$contract->division_id" col="col-md-3" />
            <x-form.select name="status" label="Status" :options="['draft' => 'Draft', 'active' => 'Active', 'completed' => 'Completed', 'terminated' => 'Terminated']" :value="$contract->status" :placeholder="false" required col="col-md-3" />
            <x-form.input name="agreement_no" label="Agreement no." :value="$contract->agreement_no" col="col-md-4" />
            <x-form.input name="agreement_date" label="Agreement date" type="date" :value="$contract->agreement_date?->toDateString()" col="col-md-4" />
            <x-form.input name="work_order_date" label="Work order date" type="date" :value="$contract->work_order_date?->toDateString()" col="col-md-4" />
            <x-form.input name="start_date" label="Contract start" type="date" :value="$contract->start_date?->toDateString()" col="col-md-3" />
            <x-form.input name="end_date" label="Contract end" type="date" :value="$contract->end_date?->toDateString()" col="col-md-3" />
            <x-form.input name="maintenance_start_date" label="Maintenance start" type="date" :value="$contract->maintenance_start_date?->toDateString()" col="col-md-3" />
            <x-form.input name="maintenance_end_date" label="Maintenance end" type="date" :value="$contract->maintenance_end_date?->toDateString()" col="col-md-3" />
            <div class="col-12">
                <div class="alert alert-info small mb-0 py-2"><i class="bi bi-info-circle me-1"></i>
                    Contractor repair responsibility is determined <strong>only</strong> by the maintenance start/end dates — not by the contract end date.
                    Draft and terminated contracts never carry responsibility.</div>
            </div>
            <x-form.input name="contract_value" label="Contract value (₹)" type="number" step="0.01" min="0" :value="$contract->contract_value" col="col-md-4" />
            <x-form.textarea name="remarks" label="Remarks" :value="$contract->remarks" col="col-md-8" rows="2" />
            <x-form.test-flag :model="$contract" />
        </div>
        <div class="card-footer bg-white"><x-form.actions :cancel="$editing ? route('contracts.show', $contract) : route('contracts.index')" /></div>
    </form>
</x-app-layout>
