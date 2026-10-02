<x-app-layout title="My profile" :breadcrumbs="['My profile']">
    <x-page-header title="My profile">
        <a href="{{ route('password.change') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-key me-1"></i>Change password</a>
    </x-page-header>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Account</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Name</dt><dd class="col-7">{{ $user->name }} <x-test-badge :model="$user" /></dd>
                        <dt class="col-5">Mobile</dt><dd class="col-7">{{ $user->mobile ?? '—' }}</dd>
                        <dt class="col-5">Email</dt><dd class="col-7">{{ $user->email ?? '—' }}</dd>
                        <dt class="col-5">Employee code</dt><dd class="col-7">{{ $user->employee_code ?? '—' }}</dd>
                        <dt class="col-5">Designation</dt><dd class="col-7">{{ $user->designation ?? '—' }}</dd>
                        @if ($user->contractor)
                            <dt class="col-5">Contractor</dt><dd class="col-7">{{ $user->contractor->name }}</dd>
                        @endif
                        <dt class="col-5">Roles</dt><dd class="col-7">{{ $user->roles->pluck('name')->join(', ') }}</dd>
                        <dt class="col-5">Password changed</dt><dd class="col-7">{{ $user->password_changed_at?->format('d-M-Y H:i') ?? 'Never' }}</dd>
                        <dt class="col-5">Last sign-in</dt><dd class="col-7">{{ $user->last_login_at?->format('d-M-Y H:i') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">My permissions</div>
                <div class="card-body">
                    @foreach ($permissions as $permission)
                        <span class="badge text-bg-light border me-1 mb-1 fw-normal">{{ $permission }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
