<?php

namespace Tests\Feature\Phase8;

use App\Models\AssetType;
use App\Models\EscalationRule;
use App\Models\IssueCategory;
use App\Models\SlaRule;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

class SlaAdminTest extends SeededTestCase
{
    #[Test]
    public function admins_manage_sla_and_escalation_rules_and_others_cannot(): void
    {
        $admin = $this->userByEmail('admin@rcd.test');
        $this->actingAs($this->userByEmail('je001@rcd.test'))->get('/admin/sla')->assertForbidden();
        $this->actingAs($admin)->get('/admin/sla')->assertOk()->assertSee('Contractor response');

        $bridgeJoint = IssueCategory::where('code', 'EXPANSION_JOINT')->firstOrFail();
        $this->actingAs($admin)->post('/admin/sla/rules', ['stage' => 'REPAIR', 'issue_category_id' => $bridgeJoint->id, 'hours' => 48])->assertRedirect();
        $rule = SlaRule::where('issue_category_id', $bridgeJoint->id)->sole();
        $this->assertSame($bridgeJoint->asset_type_id, $rule->asset_type_id); // filled from the category

        $this->actingAs($admin)->post('/admin/sla/rules', ['stage' => 'REPAIR', 'issue_category_id' => $bridgeJoint->id, 'hours' => 24])->assertSessionHasErrors('hours');
        $roadType = AssetType::where('code', 'ROAD')->value('id');
        $this->actingAs($admin)->post('/admin/sla/rules', ['stage' => 'REPAIR', 'asset_type_id' => $roadType, 'issue_category_id' => $bridgeJoint->id, 'hours' => 24])
            ->assertSessionHasErrors('issue_category_id');

        $this->actingAs($admin)->put("/admin/sla/rules/{$rule->id}", ['hours' => 36, 'is_active' => 1])->assertRedirect();
        $this->assertEquals(36, $rule->fresh()->hours);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sla_rule.updated', 'auditable_id' => $rule->id]);

        $this->actingAs($admin)->post('/admin/sla/escalations', ['trigger' => 'after_due', 'offset_hours' => 48, 'level' => 3, 'action' => 'escalate',
            'notify_role_code' => '', 'notify_assignee' => 0, 'is_active' => 1])->assertSessionHasErrors('notify_role_code');
        $this->actingAs($admin)->post('/admin/sla/escalations', ['trigger' => 'after_due', 'offset_hours' => 48, 'level' => 3, 'action' => 'escalate',
            'notify_role_code' => 'EE', 'notify_assignee' => 1, 'is_active' => 1])->assertRedirect();
        $this->assertSame(4, EscalationRule::count());
    }
}
