<x-app-layout title="Assign officer" :breadcrumbs="['Responsibility' => route('responsibility.index'), 'Assign']">
    <x-page-header title="Assign JE / AE / EE" subtitle="The officer currently mapped is closed the day before the new assignment starts. History is never overwritten." />

    <form method="POST" action="{{ route('responsibility.store') }}" class="row g-3" novalidate>
        @csrf
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">Road sections</div>
                <div class="card-body row g-3">
                    <x-form.select name="road_id" label="Road" :options="$roads" :value="$roadId" required col="col-12" />
                    <div class="col-12">
                        @error('sections')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                        <div class="border rounded p-2" style="max-height: 340px; overflow:auto"
                             data-section-checks-for="#f_road_id" data-url="{{ url('roads/:id/sections.json') }}" data-name="sections[]"
                             data-selected="{{ implode(',', old('sections', $preselected)) }}"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">Officer</div>
                <div class="card-body row g-3">
                    <x-form.select name="role_code" label="Role" :options="['JE' => 'Junior Engineer (JE)', 'AE' => 'Assistant Engineer (AE)', 'EE' => 'Executive Engineer (EE)']" required col="col-12" />
                    <div class="col-12">
                        <label class="form-label required" for="f_user_id">Officer</label>
                        <select id="f_user_id" name="user_id" data-depends-on="#f_role_code" class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">— choose role first —</option>
                            @foreach ($officers as $officer)
                                @foreach ($officer->roles->whereIn('code', ['JE', 'AE', 'EE']) as $role)
                                    <option value="{{ $officer->id }}" data-parent="{{ $role->code }}" @selected(old('user_id') == $officer->id && old('role_code') === $role->code)>
                                        {{ $officer->name }}{{ $officer->employee_code ? ' ('.$officer->employee_code.')' : '' }}{{ $officer->is_test ? ' [TEST]' : '' }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-form.input name="effective_from" label="Effective from" type="date" :value="now()->toDateString()" required col="col-12" />
                    <x-form.input name="remarks" label="Remarks / order reference" col="col-12" />
                </div>
                <div class="card-footer bg-white"><x-form.actions :cancel="route('responsibility.index')" label="Assign" /></div>
            </div>
        </div>
    </form>
</x-app-layout>
