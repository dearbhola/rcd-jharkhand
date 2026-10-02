<?php

namespace App\Domain\Users;

use App\Domain\Audit\AuditLogger;
use App\Enums\RoleCode;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleAdminService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(string $code, string $name, ?string $description): Role
    {
        return Role::create(['code' => $code, 'name' => $name, 'description' => $description, 'is_system' => false]);
    }

    /**
     * Replace a role's permissions. Super Admin is implicit-all and not editable.
     *
     * @param  list<int>  $permissionIds
     */
    public function syncPermissions(User $actor, Role $role, array $permissionIds): void
    {
        if ($role->code === RoleCode::SUPER_ADMIN->value) {
            throw ValidationException::withMessages(['permissions' => 'Super Admin always has every permission.']);
        }

        $roleManage = Permission::where('key', 'role.manage')->value('id');
        if (! $actor->isSuperAdmin() && $actor->roles->contains($role) && ! in_array($roleManage, $permissionIds, true)) {
            throw ValidationException::withMessages(['permissions' => 'You cannot remove role management from your own role.']);
        }

        DB::transaction(function () use ($role, $permissionIds) {
            $before = $role->permissions()->pluck('key')->all();
            $role->permissions()->sync($permissionIds);
            $after = $role->permissions()->pluck('key')->all();

            $added = array_values(array_diff($after, $before));
            $removed = array_values(array_diff($before, $after));

            if ($added || $removed) {
                $this->audit->log('role.permissions_changed', $role, ['removed' => $removed], ['added' => $added]);
            }
        });
    }
}
