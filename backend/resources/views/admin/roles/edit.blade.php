<x-app-layout :title="'Permissions: '.$role->name" :breadcrumbs="['Roles & permissions' => route('admin.roles.index'), $role->name]">
    <x-page-header :title="$role->name" :subtitle="'Code: '.$role->code" />

    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
        @csrf @method('PUT')
        <div class="row g-3">
            @foreach ($modules as $module => $permissions)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>{{ ['gis' => 'GIS', 'sla' => 'SLA'][$module] ?? Str::headline($module) }}</span>
                            @can('role.manage')
                                <input type="checkbox" class="form-check-input" data-check-all="{{ $module }}" title="Select all" aria-label="Select all {{ $module }}">
                            @endcan
                        </div>
                        <div class="card-body">
                            @foreach ($permissions as $permission)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="p{{ $permission->id }}"
                                           data-group="{{ $module }}" @checked(in_array($permission->id, $granted, true)) @cannot('role.manage') disabled @endcannot>
                                    <label class="form-check-label small" for="p{{ $permission->id }}">
                                        {{ $permission->description }} <code class="text-muted">{{ $permission->key }}</code>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @can('role.manage')
            <div class="mt-3 d-flex gap-2 position-sticky bottom-0 bg-body py-2">
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save permissions</button>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        @endcan
    </form>
</x-app-layout>
