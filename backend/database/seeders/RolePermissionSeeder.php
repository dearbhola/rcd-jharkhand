<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Roles and the permission catalogue. Idempotent; safe in production.
 * Role → permission grants here are defaults; admins can change them in the UI.
 */
class RolePermissionSeeder extends Seeder
{
    /** module => [action => description] */
    public const CATALOGUE = [
        'dashboard' => ['view' => 'View own dashboard'],
        'user' => ['view' => 'View users', 'create' => 'Create users', 'update' => 'Edit users', 'deactivate' => 'Deactivate users'],
        'role' => ['view' => 'View roles', 'manage' => 'Edit roles and permissions'],
        'division' => ['view' => 'View divisions', 'manage' => 'Manage divisions / sub-divisions'],
        'road' => ['view' => 'View roads', 'create' => 'Create roads', 'update' => 'Edit roads', 'delete' => 'Deactivate roads'],
        'road_section' => ['view' => 'View road sections', 'manage' => 'Create / split / edit sections'],
        'gis' => ['manage' => 'Draw, edit, import and export geometry'],
        'asset' => ['view' => 'View assets', 'create' => 'Create assets', 'update' => 'Edit assets', 'delete' => 'Deactivate assets'],
        'category' => ['manage' => 'Manage asset types, issue categories and severities'],
        'contractor' => ['view' => 'View contractors', 'create' => 'Create contractors', 'update' => 'Edit contractors'],
        'contract' => ['view' => 'View contracts', 'create' => 'Create contracts', 'update' => 'Edit contracts', 'map' => 'Map contracts to road sections'],
        'responsibility' => ['view' => 'View JE/AE/EE mapping', 'manage' => 'Change JE/AE/EE mapping'],
        'report' => [
            'create' => 'Create field reports',
            'view' => 'View own / assigned reports',
            'view_all' => 'View all reports in jurisdiction',
            'validate' => 'Validate or invalidate reports',
            'link_duplicate' => 'Link / merge duplicate reports',
            'review' => 'Perform JE/AE field review',
            'reject' => 'Reject a repair at review',
            'location_override' => 'Bypass GPS radius in test mode',
        ],
        'repair' => ['submit' => 'Acknowledge and submit repairs'],
        'approval' => ['approve' => 'Final approval', 'reject' => 'Final rejection'],
        'workflow' => ['assign' => 'Assign tasks', 'reassign' => 'Reassign tasks', 'manage' => 'Edit workflow definitions'],
        'delegation' => ['view' => 'View delegations', 'manage' => 'Create / cancel delegations'],
        'sla' => ['manage' => 'Manage SLA and escalation rules'],
        'performance' => ['view' => 'View contractor performance'],
        'analytics' => ['view' => 'View reports module', 'export' => 'Export PDF / Excel / CSV'],
        'audit' => ['view' => 'View audit logs'],
        'settings' => ['manage' => 'Manage system settings'],
        'testdata' => ['include' => 'Include test data in views'],
        'evidence' => ['view_original' => 'Download original (unwatermarked) evidence'],
        'notification' => ['view' => 'View own notifications'],
    ];

    public function run(): void
    {
        foreach (RoleCode::cases() as $code) {
            Role::updateOrCreate(['code' => $code->value], ['name' => $code->label(), 'is_system' => true]);
        }

        foreach (self::CATALOGUE as $module => $actions) {
            foreach ($actions as $action => $description) {
                Permission::updateOrCreate(
                    ['key' => "{$module}.{$action}"],
                    ['module' => $module, 'action' => $action, 'description' => $description],
                );
            }
        }

        $all = Permission::pluck('id', 'key');

        foreach ($this->grants() as $roleCode => $keys) {
            $role = Role::where('code', $roleCode)->firstOrFail();
            $ids = $keys === ['*'] ? $all->values() : collect($keys)->map(fn ($k) => $all[$k] ?? throw new \LogicException("Unknown permission {$k}"));
            // Only add defaults; never strip permissions an admin has granted since.
            $role->permissions()->syncWithoutDetaching($ids->all());
        }
    }

    /** @return array<string, list<string>> */
    private function grants(): array
    {
        $viewMasters = ['road.view', 'road_section.view', 'asset.view', 'contractor.view', 'contract.view', 'responsibility.view', 'division.view'];
        $common = ['dashboard.view', 'notification.view'];

        return [
            RoleCode::SUPER_ADMIN->value => ['*'],
            RoleCode::ADMIN->value => [
                ...$common, ...$viewMasters,
                'user.view', 'user.create', 'user.update', 'user.deactivate', 'role.view', 'role.manage',
                'division.manage', 'road.create', 'road.update', 'road.delete', 'road_section.manage', 'gis.manage',
                'asset.create', 'asset.update', 'asset.delete', 'category.manage',
                'contractor.create', 'contractor.update', 'contract.create', 'contract.update', 'contract.map',
                'responsibility.manage', 'report.view', 'report.view_all', 'report.location_override',
                'workflow.assign', 'workflow.reassign', 'workflow.manage', 'delegation.view', 'delegation.manage',
                'sla.manage', 'performance.view', 'analytics.view', 'analytics.export', 'audit.view',
                'settings.manage', 'testdata.include', 'evidence.view_original',
            ],
            RoleCode::EE->value => [
                ...$common, ...$viewMasters,
                'report.create', 'report.view', 'report.view_all', 'approval.approve', 'approval.reject',
                'workflow.reassign', 'delegation.view', 'delegation.manage', 'performance.view',
                'analytics.view', 'analytics.export', 'evidence.view_original',
            ],
            RoleCode::AE->value => [
                ...$common, ...$viewMasters,
                'report.create', 'report.view', 'report.view_all', 'report.review', 'report.reject',
                'workflow.reassign', 'delegation.view', 'delegation.manage', 'performance.view', 'analytics.view',
            ],
            RoleCode::JE->value => [
                ...$common, ...$viewMasters,
                'report.create', 'report.view', 'report.view_all', 'report.validate', 'report.link_duplicate',
                'report.review', 'report.reject', 'delegation.view', 'analytics.view',
            ],
            RoleCode::CONTRACTOR->value => [...$common, 'report.create', 'report.view', 'repair.submit'],
            RoleCode::RCD_STAFF->value => [...$common, 'road.view', 'report.create', 'report.view'],
            RoleCode::CITIZEN->value => [...$common, 'report.create', 'report.view'],
        ];
    }
}
