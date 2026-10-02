<x-app-layout title="Users" :breadcrumbs="['Administration', 'Users']">
    <x-page-header title="Users" subtitle="Staff, contractor and citizen accounts">
        @can('user.create')
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>New user</a>
        @endcan
    </x-page-header>

    <form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
        <div class="col-md-5">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search name, email, mobile, employee code">
        </div>
        <div class="col-6 col-md-3">
            <select name="role" class="form-select form-select-sm">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->code }}" @selected(($filters['role'] ?? '') === $role->code)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">Any status</option>
                @foreach (['active', 'suspended', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-search"></i> Filter</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr><th>Name</th><th>Mobile / Email</th><th>Roles</th><th>Status</th><th>Last sign-in</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $user->name }} <x-test-badge :model="$user" /></div>
                            <div class="small text-muted">{{ $user->employee_code }} {{ $user->designation }}</div>
                        </td>
                        <td class="small">{{ $user->mobile }}<div class="text-muted">{{ $user->email }}</div></td>
                        <td>
                            @foreach ($user->roles as $role)
                                <span class="badge text-bg-light border">{{ $role->code }}</span>
                            @endforeach
                            @if ($user->contractor)
                                <div class="small text-muted">{{ $user->contractor->name }}</div>
                            @endif
                        </td>
                        <td><x-status-badge :status="$user->status" /></td>
                        <td class="small text-muted">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-end"><a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No users match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-footer bg-white">{{ $users->links() }}</div>
        @endif
    </div>
</x-app-layout>
