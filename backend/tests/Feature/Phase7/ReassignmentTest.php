<?php

namespace Tests\Feature\Phase7;

use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowAssignment;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\WorkflowHelper;

class ReassignmentTest extends SeededTestCase
{
    use WorkflowHelper;

    private Report $report;

    private User $je;

    private User $ae;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
        $this->report = $this->fileReport(User::where('mobile', '9500000001')->firstOrFail());
        $this->je = User::find($this->report->currentResponsibility->je_user_id);
        $this->ae = User::find($this->report->currentResponsibility->ae_user_id);
    }

    private function reassign(User $by, User $to, string $reason = 'JE001 on field duty elsewhere')
    {
        return $this->actingAs($by)->post("/reports/{$this->report->id}/reassign", [
            'assignment_id' => WorkflowAssignment::where('is_active', true)->value('id'),
            'user_id' => $to->id,
            'reason' => $reason,
        ]);
    }

    #[Test]
    public function a_supervisor_reassigns_with_a_reason_and_the_move_is_audited(): void
    {
        $to = $this->userByEmail('je009@rcd.test');
        $seenVersion = $this->version($this->report);

        $this->reassign($this->ae, $to)->assertRedirect();

        $this->assertSame([$to->id], $this->holders($this->report));
        $new = WorkflowAssignment::where('is_active', true)->sole();
        $this->assertSame(['manual', $this->je->id, $this->ae->id], [$new->assigned_via, $new->original_user_id, $new->assigned_by]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'workflow.reassigned', 'auditable_id' => $this->report->id, 'comment' => 'JE001 on field duty elsewhere']);
        $this->assertTrue($to->notifications()->where('data->event', 'task.reassigned')->exists());

        // The previous holder's open page is now stale.
        $this->act($this->je, $this->report, 'validate', version: $seenVersion)->assertStatus(409);
        $this->act($to, $this->report, 'validate')->assertOk();
    }

    #[Test]
    public function reassignment_rules(): void
    {
        $this->reassign($this->je, $this->userByEmail('je009@rcd.test'))->assertForbidden();       // JE lacks workflow.reassign
        $this->reassign($this->ae, $this->ae)->assertSessionHasErrors('user_id');                    // AE cannot take a JE step
        $this->reassign($this->ae, $this->userByEmail('je009@rcd.test'), 'no')->assertSessionHasErrors('reason');
        $this->reassign($this->ae, $this->je)->assertSessionHasErrors('user_id');                    // already the holder
        $this->assertSame([$this->je->id], $this->holders($this->report));
    }

    #[Test]
    public function contractor_tasks_move_only_within_the_same_firm(): void
    {
        $this->act($this->je, $this->report, 'validate')->assertOk();
        $firm = $this->report->currentResponsibility->contractor_id;
        $otherFirmUser = User::whereNotNull('contractor_id')->where('contractor_id', '!=', $firm)->first();

        $this->reassign($this->ae, $otherFirmUser)->assertSessionHasErrors('user_id');

        $colleague = User::factory()->create(['contractor_id' => $firm, 'status' => 'active']);
        $colleague->roles()->attach(Role::where('code', 'CONTRACTOR')->value('id'));
        $this->reassign($this->ae, $colleague)->assertRedirect();
        $this->assertSame([$colleague->id], $this->holders($this->report));
    }

    #[Test]
    public function the_report_page_offers_reassignment_only_to_supervisors(): void
    {
        $this->actingAs($this->ae)->get("/reports/{$this->report->id}")->assertOk()->assertSee('Reassign task');
        $this->actingAs($this->je)->get("/reports/{$this->report->id}")->assertOk()->assertDontSee('Reassign task');
    }
}
