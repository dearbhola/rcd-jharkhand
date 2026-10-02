<?php

namespace Tests\Feature\Phase1;

use App\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\Road;
use App\Models\RoadGeometry;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

class IntegrityTest extends SeededTestCase
{
    #[Test]
    public function master_data_changes_are_audited_with_old_and_new_values(): void
    {
        $admin = $this->userByEmail('admin@rcd.test');
        $this->actingAs($admin);
        $road = $this->road('RCD-003');

        $road->update(['name' => 'Renamed Road']);

        $log = AuditLog::where('auditable_type', 'road')->where('auditable_id', $road->id)->where('action', 'road.updated')->latest('id')->firstOrFail();
        $this->assertSame(['name' => 'Demo Road 3 (SDN1)'], $log->old_values);
        $this->assertSame('Renamed Road', $log->new_values['name']);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('ADMIN', $log->role_code);
        $this->assertTrue($log->is_test);
        $this->assertSame($admin->id, $road->fresh()->updated_by);
    }

    #[Test]
    public function passwords_never_reach_the_audit_trail(): void
    {
        $user = $this->userByEmail('je001@rcd.test');
        $user->update(['password' => 'Another#Pass123']);

        $log = AuditLog::where('auditable_type', 'user')->where('auditable_id', $user->id)->latest('id')->first();
        $this->assertTrue($log === null || ! array_key_exists('password', $log->new_values ?? []));
    }

    #[Test]
    public function audit_logs_cannot_be_modified_or_deleted(): void
    {
        $log = AuditLog::create(['action' => 'test.event', 'user_name' => 'system']);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    #[Test]
    public function audit_logs_cannot_be_deleted(): void
    {
        $log = AuditLog::create(['action' => 'test.event', 'user_name' => 'system']);

        $this->expectException(LogicException::class);
        $log->delete();
    }

    #[Test]
    public function road_geometry_versions_are_immutable(): void
    {
        $geometry = RoadGeometry::where('is_current', true)->firstOrFail();

        $this->expectException(LogicException::class);
        $geometry->update(['geojson' => '{}']);
    }

    #[Test]
    public function workflow_history_is_append_only(): void
    {
        $step = WorkflowStep::firstOrFail();
        $report = Report::factory()->create();
        $instance = WorkflowInstance::create([
            'report_id' => $report->id, 'workflow_definition_id' => $step->workflow_definition_id,
            'current_step_id' => $step->id, 'started_at' => now(), 'is_test' => true,
        ]);
        $action = WorkflowAction::create([
            'workflow_instance_id' => $instance->id, 'action_code' => 'test', 'to_step_id' => $step->id,
        ]);

        $this->expectException(LogicException::class);
        $action->update(['comment' => 'rewritten']);
    }

    #[Test]
    public function rbac_grants_follow_the_role_matrix(): void
    {
        $citizen = User::whereHas('roles', fn ($q) => $q->where('code', RoleCode::CITIZEN->value))->firstOrFail();
        $je = $this->userByEmail('je001@rcd.test');
        $ee = $this->userByEmail('ee.dn@rcd.test');
        $super = $this->userByEmail('superadmin@rcd.test');

        $this->assertTrue($citizen->hasPermission('report.create'));
        $this->assertFalse($citizen->hasPermission('report.validate'));
        $this->assertFalse($citizen->hasPermission('approval.approve'));

        $this->assertTrue($je->hasPermission('report.validate'));
        $this->assertTrue($je->hasPermission('report.reject'));
        $this->assertFalse($je->hasPermission('approval.approve'));

        $this->assertTrue($ee->hasPermission('approval.approve'));
        $this->assertFalse($ee->hasPermission('repair.submit'));

        $this->assertTrue($super->hasPermission('anything.at_all'));
        $this->assertSame(RoleCode::EE, $ee->primaryRoleCode());
    }

    #[Test]
    public function soft_deleted_roads_are_kept(): void
    {
        $road = $this->road('RCD-020');
        $road->delete();

        $this->assertNull(Road::find($road->id));
        $this->assertNotNull(Road::withTrashed()->find($road->id));
    }
}
