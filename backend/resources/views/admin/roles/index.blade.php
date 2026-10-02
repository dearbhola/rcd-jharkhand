<x-app-layout title="Roles & permissions" :breadcrumbs="['Administration', 'Roles & permissions']">
    <x-page-header title="Roles & permissions" subtitle="Database-driven RBAC. Permissions follow the module.action pattern." />

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Role</th><th>Code</th><th class="text-end">Users</th><th class="text-end">Permissions</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td class="fw-semibold">{{ $role->name }} @if ($role->is_system)<span class="badge text-bg-light border ms-1">system</span>@endif</td>
                                <td><code>{{ $role->code }}</code></td>
                                <td class="text-end">{{ $role->users_count }}</td>
                                <td class="text-end">{{ $role->code === 'SUPER_ADMIN' ? 'All' : $role->permissions_count }}</td>
                                <td class="text-end">
                                    @if ($role->code !== 'SUPER_ADMIN')
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">Permissions</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @can('role.manage')
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">New role</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.roles.store') }}" novalidate>
                            @csrf
                            <div class="mb-2">
                                <label class="form-label required" for="code">Code</label>
                                <input id="code" name="code" value="{{ old('code') }}" class="form-control text-uppercase @error('code') is-invalid @enderror" placeholder="e.g. DIVISION_CLERK">
                            </div>
                            <div class="mb-2">
                                <label class="form-label required" for="rname">Name</label>
                                <input id="rname" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="description">Description</label>
                                <input id="description" name="description" value="{{ old('description') }}" class="form-control">
                            </div>
                            <button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create role</button>
                            <div class="form-text">Workflow steps refer to the system roles (JE, AE, EE, Contractor); custom roles grant screen/data permissions only.</div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>
</x-app-layout>
