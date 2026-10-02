<?php

namespace App\Domain\Users;

use App\Domain\Audit\AuditLogger;
use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff account administration. Role changes are audited explicitly because
 * pivot changes are not covered by the Auditable trait.
 */
class UserAdminService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  validated attributes
     * @param  list<int>  $roleIds
     */
    public function create(User $actor, array $data, array $roleIds): User
    {
        $this->assertCanAssignRoles($actor, $roleIds);
        $this->assertContractorLink($roleIds, $data['contractor_id'] ?? null);

        return DB::transaction(function () use ($actor, $data, $roleIds) {
            $user = User::create([
                ...$data,
                'password_changed_at' => null, // set by an administrator → must change at first login
                'status' => User::STATUS_ACTIVE,
            ]);
            $this->syncRoles($actor, $user, $roleIds, initial: true);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $roleIds
     */
    public function update(User $actor, User $user, array $data, array $roleIds): User
    {
        $this->assertCanManage($actor, $user);
        $this->assertCanAssignRoles($actor, $roleIds);
        $this->assertContractorLink($roleIds, $data['contractor_id'] ?? null);

        if ($actor->is($user) && ! $this->containsRole($roleIds, $user->roles->pluck('id')->all())) {
            // Prevent locking yourself out of administration by accident.
            throw ValidationException::withMessages(['roles' => 'You cannot remove your own roles.']);
        }

        return DB::transaction(function () use ($actor, $user, $data, $roleIds) {
            $user->update($data);
            $this->syncRoles($actor, $user, $roleIds);

            return $user;
        });
    }

    public function setStatus(User $actor, User $user, string $status, string $reason): void
    {
        $this->assertCanManage($actor, $user);

        if ($actor->is($user)) {
            throw ValidationException::withMessages(['status' => 'You cannot change your own account status.']);
        }

        DB::transaction(function () use ($user, $status, $reason) {
            $old = $user->status;
            $user->forceFill(['status' => $status])->saveQuietly();

            if ($status !== User::STATUS_ACTIVE) {
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $this->audit->log('user.status_changed', $user, ['status' => $old], ['status' => $status], $reason);
        });
    }

    public function resetPassword(User $actor, User $user, string $temporaryPassword, string $reason): void
    {
        $this->assertCanManage($actor, $user);

        $user->forceFill(['password' => $temporaryPassword, 'password_changed_at' => null])->saveQuietly();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $this->audit->log('user.password_reset', $user, null, null, $reason);
    }

    public function assertCanManage(User $actor, User $user): void
    {
        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Only a Super Admin can manage a Super Admin account.');
        }
    }

    /** @param list<int> $roleIds */
    private function assertCanAssignRoles(User $actor, array $roleIds): void
    {
        $superId = Role::where('code', RoleCode::SUPER_ADMIN->value)->value('id');

        if (in_array($superId, $roleIds, true) && ! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Only a Super Admin can grant the Super Admin role.');
        }
    }

    /** @param list<int> $roleIds */
    private function assertContractorLink(array $roleIds, mixed $contractorId): void
    {
        $contractorRoleId = Role::where('code', RoleCode::CONTRACTOR->value)->value('id');
        $isContractor = in_array($contractorRoleId, $roleIds, true);

        if ($isContractor && ! $contractorId) {
            throw ValidationException::withMessages(['contractor_id' => 'A contractor user must be linked to a contractor.']);
        }
        if (! $isContractor && $contractorId) {
            throw ValidationException::withMessages(['contractor_id' => 'Only users with the Contractor role can be linked to a contractor.']);
        }
    }

    /** @param list<int> $roleIds */
    private function syncRoles(User $actor, User $user, array $roleIds, bool $initial = false): void
    {
        $before = $user->roles()->pluck('code')->sort()->values()->all();
        $current = $user->roles()->pluck('roles.id')->all();

        // Detach removed roles; attach new ones with who/when. Unchanged roles keep their original pivot data.
        $user->roles()->detach(array_diff($current, $roleIds));
        foreach (array_diff($roleIds, $current) as $roleId) {
            $user->roles()->attach($roleId, ['assigned_by' => $actor->id, 'assigned_at' => now()]);
        }
        $user->flushPermissionCache();

        $after = $user->roles()->pluck('code')->sort()->values()->all();
        if ($initial || $before !== $after) {
            $this->audit->log('user.roles_changed', $user, ['roles' => $before], ['roles' => $after]);
        }
    }

    /**
     * True if every role in $required is present in $roleIds.
     *
     * @param  list<int>  $roleIds
     * @param  list<int>  $required
     */
    private function containsRole(array $roleIds, array $required): bool
    {
        return array_diff($required, $roleIds) === [];
    }
}
