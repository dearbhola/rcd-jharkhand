<?php

namespace Tests\Feature\Phase2;

use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

class AuthorizationTest extends SeededTestCase
{
    private function citizen(): User
    {
        return User::where('mobile', '9500000001')->firstOrFail();
    }

    private function newUserPayload(array $roleCodes, array $overrides = []): array
    {
        return [
            'name' => 'New Engineer',
            'mobile' => '9123400001',
            'email' => 'new.engineer@rcd.test',
            'employee_code' => 'JE900',
            'designation' => 'Junior Engineer',
            'roles' => Role::whereIn('code', $roleCodes)->pluck('id')->all(),
            'password' => 'Temp#Pass2026',
            'password_confirmation' => 'Temp#Pass2026',
            ...$overrides,
        ];
    }

    #[Test]
    public function admin_screens_are_forbidden_without_permission(): void
    {
        foreach (['/admin/users', '/admin/roles', '/admin/audit'] as $url) {
            $this->actingAs($this->citizen())->get($url)->assertForbidden();
            $this->actingAs($this->userByEmail('je001@rcd.test'))->get($url)->assertForbidden();
            $this->actingAs($this->userByEmail('admin@rcd.test'))->get($url)->assertOk();
        }
    }

    #[Test]
    public function the_sidebar_only_lists_permitted_modules(): void
    {
        $this->actingAs($this->citizen())->get('/dashboard')->assertOk()->assertDontSee('Roles &amp; Permissions', false)->assertDontSee('Audit Log');
        $this->actingAs($this->userByEmail('admin@rcd.test'))->get('/dashboard')->assertSee('Roles &amp; Permissions', false)->assertSee('Audit Log');
    }

    #[Test]
    public function admins_create_staff_with_audited_roles_and_a_forced_password_change(): void
    {
        $this->actingAs($this->userByEmail('admin@rcd.test'))
            ->post('/admin/users', $this->newUserPayload(['JE']))
            ->assertRedirect();

        $user = User::where('mobile', '9123400001')->firstOrFail();
        $this->assertSame(['JE'], $user->roleCodes()->all());
        $this->assertNull($user->password_changed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'auditable_id' => $user->id]);
        $log = AuditLog::where('action', 'user.roles_changed')->where('auditable_id', $user->id)->sole();
        $this->assertSame(['roles' => ['JE']], $log->new_values);
    }

    #[Test]
    public function only_a_super_admin_can_grant_the_super_admin_role(): void
    {
        $this->actingAs($this->userByEmail('admin@rcd.test'))
            ->post('/admin/users', $this->newUserPayload(['SUPER_ADMIN']))
            ->assertForbidden();

        $this->actingAs($this->userByEmail('superadmin@rcd.test'))
            ->post('/admin/users', $this->newUserPayload(['SUPER_ADMIN']))
            ->assertRedirect();
    }

    #[Test]
    public function admins_cannot_edit_super_admin_accounts(): void
    {
        $super = $this->userByEmail('superadmin@rcd.test');

        $this->actingAs($this->userByEmail('admin@rcd.test'))->get("/admin/users/{$super->id}/edit")->assertForbidden();
    }

    #[Test]
    public function contractor_users_must_be_linked_to_a_contractor(): void
    {
        $admin = $this->userByEmail('admin@rcd.test');

        $this->actingAs($admin)->post('/admin/users', $this->newUserPayload(['CONTRACTOR']))->assertSessionHasErrors('contractor_id');
        $this->actingAs($admin)->post('/admin/users', $this->newUserPayload(['JE'], ['contractor_id' => Contractor::value('id')]))->assertSessionHasErrors('contractor_id');
        $this->actingAs($admin)->post('/admin/users', $this->newUserPayload(['CONTRACTOR'], ['contractor_id' => Contractor::value('id')]))->assertRedirect();
    }

    #[Test]
    public function suspending_a_user_requires_a_reason_and_is_audited(): void
    {
        $admin = $this->userByEmail('admin@rcd.test');
        $je = $this->userByEmail('je001@rcd.test');

        $this->actingAs($admin)->put("/admin/users/{$je->id}/status", ['status' => 'suspended'])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->put("/admin/users/{$je->id}/status", ['status' => 'suspended', 'reason' => 'On long leave'])->assertRedirect();

        $this->assertSame('suspended', $je->fresh()->status);
        $log = AuditLog::where('action', 'user.status_changed')->where('auditable_id', $je->id)->sole();
        $this->assertSame('On long leave', $log->comment);
        $this->assertSame(['status' => 'active'], $log->old_values);
    }

    #[Test]
    public function admins_cannot_change_their_own_status(): void
    {
        $admin = $this->userByEmail('admin@rcd.test');

        $this->actingAs($admin)->put("/admin/users/{$admin->id}/status", ['status' => 'suspended', 'reason' => 'testing self'])->assertSessionHasErrors('status');
    }

    #[Test]
    public function role_permission_changes_take_effect_and_are_audited(): void
    {
        $je = $this->userByEmail('je001@rcd.test');
        $role = Role::where('code', 'JE')->firstOrFail();
        $auditView = Permission::where('key', 'audit.view')->value('id');
        $current = $role->permissions()->pluck('permissions.id')->all();

        $this->actingAs($this->userByEmail('admin@rcd.test'))
            ->put("/admin/roles/{$role->id}", ['permissions' => [...$current, $auditView]])
            ->assertRedirect('/admin/roles');

        $this->actingAs($je->fresh())->get('/admin/audit')->assertOk();
        $log = AuditLog::where('action', 'role.permissions_changed')->where('auditable_id', $role->id)->sole();
        $this->assertSame(['added' => ['audit.view']], $log->new_values);
    }

    #[Test]
    public function the_super_admin_role_cannot_be_edited(): void
    {
        $role = Role::where('code', 'SUPER_ADMIN')->firstOrFail();

        $this->actingAs($this->userByEmail('superadmin@rcd.test'))
            ->put("/admin/roles/{$role->id}", ['permissions' => []])
            ->assertSessionHasErrors('permissions');
    }

    #[Test]
    public function only_permitted_users_can_toggle_test_data_and_it_filters_lists(): void
    {
        $this->actingAs($this->userByEmail('je001@rcd.test'))->post('/test-data/toggle')->assertForbidden();

        $admin = $this->userByEmail('admin@rcd.test');
        $this->actingAs($admin)->get('/admin/users')->assertSee('je001@rcd.test');

        $this->actingAs($admin)->post('/test-data/toggle')->assertRedirect();
        $this->actingAs($admin)->get('/admin/users')->assertDontSee('je001@rcd.test');
        $this->assertDatabaseHas('audit_logs', ['action' => 'testdata.toggled']);
    }
}
