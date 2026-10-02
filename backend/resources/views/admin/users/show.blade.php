<x-app-layout :title="$user->name" :breadcrumbs="['Users' => route('admin.users.index'), $user->name]">
    <x-page-header :title="$user->name" :subtitle="$user->designation">
        @can('user.update')
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endcan
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">Account <x-status-badge :status="$user->status" /></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Mobile</dt><dd class="col-7">{{ $user->mobile ?? '—' }}</dd>
                        <dt class="col-5">Email</dt><dd class="col-7">{{ $user->email ?? '—' }}</dd>
                        <dt class="col-5">Employee code</dt><dd class="col-7">{{ $user->employee_code ?? '—' }}</dd>
                        <dt class="col-5">Roles</dt><dd class="col-7">{{ $user->roles->pluck('name')->join(', ') }}</dd>
                        @if ($user->contractor)
                            <dt class="col-5">Contractor</dt><dd class="col-7">{{ $user->contractor->name }}</dd>
                        @endif
                        <dt class="col-5">Data</dt><dd class="col-7">@if ($user->is_test)<x-test-badge :model="$user" />@else Production @endif</dd>
                        <dt class="col-5">Last sign-in</dt><dd class="col-7">{{ $user->last_login_at?->format('d-M-Y H:i') ?? '—' }}</dd>
                        <dt class="col-5">Password changed</dt><dd class="col-7">{{ $user->password_changed_at?->format('d-M-Y H:i') ?? 'Must change at next sign-in' }}</dd>
                    </dl>
                </div>
            </div>

            @can('user.deactivate')
                <div class="card mb-3">
                    <div class="card-header">Change status</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.users.status', $user) }}" class="row g-2" data-confirm="Change this account's status?">
                            @csrf @method('PUT')
                            <div class="col-5">
                                <select name="status" class="form-select form-select-sm">
                                    @foreach (['active', 'suspended', 'inactive'] as $status)
                                        <option value="{{ $status }}" @selected($user->status === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-7"><input name="reason" class="form-control form-control-sm" placeholder="Reason (required)" required minlength="5"></div>
                            <div class="col-12"><button class="btn btn-sm btn-warning">Update status</button></div>
                        </form>
                    </div>
                </div>
            @endcan

            @can('user.update')
                <div class="card">
                    <div class="card-header">Set temporary password</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.users.password', $user) }}" class="row g-2" data-confirm="Reset this user's password?">
                            @csrf @method('PUT')
                            <div class="col-6"><input type="password" name="password" class="form-control form-control-sm" placeholder="New password" autocomplete="new-password" required></div>
                            <div class="col-6"><input type="password" name="password_confirmation" class="form-control form-control-sm" placeholder="Confirm" autocomplete="new-password" required></div>
                            <div class="col-12"><input name="reason" class="form-control form-control-sm" placeholder="Reason (required)" required minlength="5"></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-danger">Reset password</button></div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Account history</div>
                <div class="list-group list-group-flush small">
                    @forelse ($history as $entry)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">{{ $entry->action }}</span>
                                <span class="text-muted">{{ $entry->created_at->format('d-M-Y H:i') }}</span>
                            </div>
                            <div class="text-muted">by {{ $entry->user_name }} {{ $entry->role_code ? '('.$entry->role_code.')' : '' }}</div>
                            @if ($entry->comment)<div>“{{ $entry->comment }}”</div>@endif
                            @can('audit.view')
                                <a href="{{ route('admin.audit.show', $entry) }}" class="small">Details</a>
                            @endcan
                        </div>
                    @empty
                        <div class="list-group-item text-muted">No recorded history.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
