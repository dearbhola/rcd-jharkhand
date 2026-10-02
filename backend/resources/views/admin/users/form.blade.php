@php($editing = $user->exists)
<x-app-layout :title="$editing ? 'Edit user' : 'New user'"
              :breadcrumbs="['Users' => route('admin.users.index'), ($editing ? 'Edit' : 'New')]">
    <x-page-header :title="$editing ? 'Edit '.$user->name : 'New user'" />

    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        @php($selectedRoles = collect(old('roles', $user->roles?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id))

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">Details</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="name">Full name</label>
                            <input id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="mobile">Mobile</label>
                            <input id="mobile" name="mobile" value="{{ old('mobile', $user->mobile) }}" inputmode="numeric" maxlength="10" class="form-control @error('mobile') is-invalid @enderror" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="employee_code">Employee code</label>
                            <input id="employee_code" name="employee_code" value="{{ old('employee_code', $user->employee_code) }}" class="form-control @error('employee_code') is-invalid @enderror">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="designation">Designation</label>
                            <input id="designation" name="designation" value="{{ old('designation', $user->designation) }}" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contractor_id">Contractor <span class="text-muted small">(contractor users only)</span></label>
                            <select id="contractor_id" name="contractor_id" class="form-select @error('contractor_id') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach ($contractors as $contractor)
                                    <option value="{{ $contractor->id }}" @selected((int) old('contractor_id', $user->contractor_id) === $contractor->id)>
                                        {{ $contractor->name }}{{ $contractor->is_test ? ' [TEST]' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        @unless ($editing)
                            <div class="col-md-6">
                                <label class="form-label required" for="password">Temporary password</label>
                                <input id="password" type="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="password_confirmation">Confirm password</label>
                                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="form-control" required>
                            </div>
                            <div class="col-12 form-text mt-0">The user must change this password at first sign-in.</div>
                        @endunless
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">Roles</div>
                    <div class="card-body">
                        @error('roles') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        @foreach ($roles as $role)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role{{ $role->id }}"
                                       @checked($selectedRoles->contains($role->id))>
                                <label class="form-check-label" for="role{{ $role->id }}">
                                    {{ $role->name }} <span class="text-muted small">({{ $role->code }})</span>
                                </label>
                            </div>
                        @endforeach
                        <div class="form-text">JE/AE/EE road responsibility is assigned separately under Responsibility mapping.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $editing ? 'Save changes' : 'Create user' }}</button>
            <a href="{{ $editing ? route('admin.users.show', $user) : route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
