<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Users\RoleAdminService;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly RoleAdminService $roles) {}

    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z][A-Z0-9_]+$/', Rule::unique('roles', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $role = $this->roles->create($data['code'], $data['name'], $data['description'] ?? null);

        return redirect()->route('admin.roles.edit', $role)->with('success', 'Role created. Choose its permissions.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role,
            'granted' => $role->permissions()->pluck('permissions.id')->all(),
            'modules' => Permission::orderBy('module')->orderBy('id')->get()->groupBy('module'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $this->roles->syncPermissions($request->user(), $role, array_map('intval', $data['permissions'] ?? []));

        return redirect()->route('admin.roles.index')->with('success', "Permissions for {$role->name} saved.");
    }
}
