<x-app-layout :title="'Edit '.$section->code"
              :breadcrumbs="['Roads' => route('roads.index'), $section->road->code => route('roads.show', $section->road), 'Edit '.$section->code]">
    <x-page-header :title="'Edit section '.$section->road->code.' / '.$section->code" />
    <form method="POST" action="{{ route('road-sections.update', $section) }}" class="card" novalidate>
        @csrf @method('PUT')
        <div class="card-body row g-3">
            <x-form.input name="code" label="Code" :value="$section->code" required col="col-md-2" />
            <x-form.input name="name" label="Name" :value="$section->name" col="col-md-4" />
            <x-form.input name="start_km" label="Start km" type="number" step="0.001" :value="km($section->start_chainage_m)" required col="col-md-3" error-key="start_chainage_m" />
            <x-form.input name="end_km" label="End km" type="number" step="0.001" :value="km($section->end_chainage_m)" required col="col-md-3" error-key="end_chainage_m" />
            <x-form.select name="division_id" label="Division" :options="$divisions" :value="$section->division_id" required col="col-md-4" />
            @include('masters.roads._sub-division-select', ['value' => $section->sub_division_id, 'col' => 'col-md-4'])
            <x-form.select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$section->status" :placeholder="false" required col="col-md-4" />
            <div class="col-12 form-text">Changing chainage re-cuts the section geometry. Existing reports keep the section they were filed against.</div>
        </div>
        <div class="card-footer bg-white"><x-form.actions :cancel="route('roads.show', $section->road)" /></div>
    </form>
</x-app-layout>
