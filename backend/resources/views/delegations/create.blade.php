<x-app-layout title="New delegation" :breadcrumbs="['Delegations' => route('delegations.index'), 'New']">
    <x-page-header title="New delegation" subtitle="While it is in force, new tasks for the officer's role go to the stand-in." />
    <form method="POST" action="{{ route('delegations.store') }}" class="card" novalidate>
        @csrf
        <div class="card-body row g-3">
            <x-form.select name="role_code" label="Duty (role)" :options="['JE' => 'Junior Engineer (JE)', 'AE' => 'Assistant Engineer (AE)', 'EE' => 'Executive Engineer (EE)']" required col="col-md-4" />
            <div class="col-md-4">
                <label class="form-label required" for="f_primary_user_id">Officer away</label>
                <select id="f_primary_user_id" name="primary_user_id" data-depends-on="#f_role_code" class="form-select @error('primary_user_id') is-invalid @enderror" required>
                    <option value="">— choose duty first —</option>
                    @foreach ($officers as $o)
                        @foreach ($o->roles->whereIn('code', ['JE', 'AE', 'EE']) as $r)
                            <option value="{{ $o->id }}" data-parent="{{ $r->code }}" @selected(old('primary_user_id', $primaryId) == $o->id)>{{ $o->name }}{{ $o->employee_code ? ' ('.$o->employee_code.')' : '' }}</option>
                        @endforeach
                    @endforeach
                </select>
                @error('primary_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label required" for="f_delegate_user_id">Stand-in</label>
                <select id="f_delegate_user_id" name="delegate_user_id" data-depends-on="#f_role_code" class="form-select @error('delegate_user_id') is-invalid @enderror" required>
                    <option value="">— choose duty first —</option>
                    @foreach ($officers as $o)
                        @foreach ($o->roles->whereIn('code', ['JE', 'AE', 'EE']) as $r)
                            <option value="{{ $o->id }}" data-parent="{{ $r->code }}" @selected(old('delegate_user_id') == $o->id)>{{ $o->name }}{{ $o->employee_code ? ' ('.$o->employee_code.')' : '' }}</option>
                        @endforeach
                    @endforeach
                </select>
                @error('delegate_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <x-form.input name="starts_at" label="From" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required col="col-md-4" />
            <x-form.input name="ends_at" label="Until" type="datetime-local" :value="now()->addWeek()->setTime(18, 0)->format('Y-m-d\TH:i')" required col="col-md-4" />
            <x-form.input name="reason" label="Reason" required col="col-md-4" placeholder="e.g. Earned leave, order no. 123" />
            <div class="col-12">
                <label class="form-label required">Tasks the officer already holds</label>
                @foreach ([
                    'all_pending' => ['Move all pending tasks to the stand-in now', 'Best for planned leave.'],
                    'new_only' => ['Keep existing tasks with the officer; route only new tasks', 'For short absences where open work can wait.'],
                    'selective' => ['Let me choose which tasks move', 'After saving, pick tasks on the delegation page.'],
                ] as $value => [$label, $hint])
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="transfer_mode" id="m_{{ $value }}" value="{{ $value }}" @checked(old('transfer_mode', 'all_pending') === $value)>
                        <label class="form-check-label" for="m_{{ $value }}">{{ $label }} <span class="text-muted small">— {{ $hint }}</span></label>
                    </div>
                @endforeach
                @error('transfer_mode')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input type="hidden" name="return_on_end" value="0">
                    <input class="form-check-input" type="checkbox" name="return_on_end" value="1" id="f_return" @checked(old('return_on_end', true))>
                    <label class="form-check-label" for="f_return">When the delegation ends, return still-open delegated tasks to the officer</label>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white"><x-form.actions :cancel="route('delegations.index')" label="Save delegation" /></div>
    </form>
</x-app-layout>
