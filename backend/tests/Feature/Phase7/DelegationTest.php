<?php

namespace Tests\Feature\Phase7;

use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\Report;
use App\Models\ResponsibilityAssignment;
use App\Models\User;
use App\Models\WorkflowAssignment;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\WorkflowHelper;

class DelegationTest extends SeededTestCase
{
    use WorkflowHelper;

    private Report $report;

    private User $je;

    private User $standIn;

    private User $ae;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
        $this->report = $this->fileReport($this->citizen());
        $this->je = User::find($this->report->currentResponsibility->je_user_id);
        $this->ae = User::find($this->report->currentResponsibility->ae_user_id);
        $this->standIn = $this->userByEmail('je009@rcd.test');
    }

    private function citizen(): User
    {
        return User::where('mobile', '9500000001')->firstOrFail();
    }

    private function delegate(User $from, User $to, string $mode, array $overrides = [], ?User $by = null)
    {
        return $this->actingAs($by ?? $this->ae)->post('/delegations', [
            'primary_user_id' => $from->id,
            'delegate_user_id' => $to->id,
            'role_code' => $overrides['role_code'] ?? 'JE',
            'starts_at' => $overrides['starts_at'] ?? now()->subMinute()->toDateTimeString(),
            'ends_at' => $overrides['ends_at'] ?? now()->addDays(7)->toDateTimeString(),
            'reason' => 'Earned leave, order 12/2026',
            'transfer_mode' => $mode,
            'return_on_end' => $overrides['return_on_end'] ?? 1,
        ]);
    }

    private function delegation(): Delegation
    {
        return Delegation::latest('id')->firstOrFail();
    }

    #[Test]
    public function all_pending_moves_existing_tasks_and_keeps_the_permanent_mapping(): void
    {
        $this->delegate($this->je, $this->standIn, 'all_pending')->assertRedirect()
            ->assertSessionHas('success', 'Delegation is active. New tasks now go to the stand-in.');
        $delegation = $this->delegation();

        $this->assertSame('active', $delegation->status);
        $this->assertSame([$this->standIn->id], $this->holders($this->report));
        $new = WorkflowAssignment::where('is_active', true)->where('user_id', $this->standIn->id)->sole();
        $this->assertSame(['delegation', $this->je->id, $delegation->id], [$new->assigned_via, $new->original_user_id, $new->delegation_id]);

        // The original assignment is ended, never deleted, and linked.
        $old = WorkflowAssignment::find($new->previous_assignment_id);
        $this->assertSame([false, 'delegated', $this->je->id], [$old->is_active, $old->end_reason, $old->user_id]);

        // Stand-in can act and see the report; the officer on leave can no longer act.
        $this->act($this->je, $this->report, 'validate')->assertForbidden();
        $this->actingAs($this->standIn)->get("/reports/{$this->report->id}")->assertOk()->assertSee('for '.$this->je->name);
        $this->act($this->standIn, $this->report, 'validate')->assertOk();

        // Permanent mapping untouched.
        $this->assertSame($this->je->id, ResponsibilityAssignment::forScope('road_section', $this->report->road_section_id)
            ->where('role_code', 'JE')->effectiveOn(now())->sole()->user_id);
        $this->assertTrue(AuditLog::where('action', 'workflow.reassigned')->where('auditable_id', $this->report->id)->exists());
    }

    #[Test]
    public function new_only_leaves_existing_tasks_but_routes_new_ones_to_the_stand_in(): void
    {
        $this->delegate($this->je, $this->standIn, 'new_only')->assertRedirect();

        $this->assertSame([$this->je->id], $this->holders($this->report));
        $newReport = $this->fileReport(User::where('mobile', '9500000002')->first(), 'RCD-005', 9000);
        $this->assertSame([$this->standIn->id], $this->holders($newReport));
        $this->assertSame('delegation', WorkflowAssignment::where('is_active', true)->where('user_id', $this->standIn->id)->sole()->assigned_via);
    }

    #[Test]
    public function selective_moves_only_the_chosen_tasks(): void
    {
        $second = $this->fileReport(User::where('mobile', '9500000002')->first(), 'RCD-005', 9000); // before leave → JE
        $this->delegate($this->je, $this->standIn, 'selective')->assertRedirect();
        $delegation = $this->delegation();
        $this->assertSame([$this->je->id], $this->holders($this->report));

        $pick = WorkflowAssignment::where('is_active', true)->where('user_id', $this->je->id)
            ->whereHas('instance', fn ($q) => $q->where('report_id', $second->id))->value('id');
        $this->actingAs($this->ae)->get("/delegations/{$delegation->id}")->assertOk()->assertSee($second->report_no);
        $this->actingAs($this->ae)->post("/delegations/{$delegation->id}/transfer", ['assignments' => [$pick]])->assertRedirect();

        $this->assertSame([$this->je->id], $this->holders($this->report));
        $this->assertSame([$this->standIn->id], $this->holders($second));

        $foreign = WorkflowAssignment::where('user_id', '!=', $this->je->id)->value('id');
        $this->actingAs($this->ae)->post("/delegations/{$delegation->id}/transfer", ['assignments' => [$foreign]])->assertSessionHasErrors('assignments');
    }

    #[Test]
    public function an_ae_on_leave_has_reviews_routed_to_the_stand_in(): void
    {
        $standInAe = $this->userByEmail('ae.sdn2@rcd.test');
        $this->delegate($this->ae, $standInAe, 'new_only', ['role_code' => 'AE'], $this->userByEmail('ee.dn@rcd.test'))->assertRedirect();
        $contractor = User::where('contractor_id', $this->report->currentResponsibility->contractor_id)->first();

        $this->act($this->je, $this->report, 'validate')->assertOk();
        $this->act($contractor, $this->report, 'submit_repair', ['repair_description' => 'Patched', 'evidence' => $this->photos(2, 50)])->assertOk();
        $this->act($this->je, $this->report, 'accept', ['evidence' => $this->photos(1, 51)])->assertOk();

        $this->assertSame([$standInAe->id], $this->holders($this->report));
        $this->act($this->ae, $this->report, 'accept', ['evidence' => $this->photos(1, 52)])->assertForbidden();
        $this->act($standInAe, $this->report, 'accept', ['evidence' => $this->photos(1, 53)])->assertOk();
        $this->assertSame('EE_APPROVAL', $this->report->refresh()->status);
    }

    #[Test]
    public function ending_a_delegation_returns_open_tasks_when_configured(): void
    {
        $this->delegate($this->je, $this->standIn, 'all_pending')->assertRedirect();
        $delegation = $this->delegation();

        $this->actingAs($this->ae)->post("/delegations/{$delegation->id}/end", ['reason' => 'Officer rejoined early'])->assertRedirect();

        $this->assertSame('ended', $delegation->fresh()->status);
        $this->assertSame([$this->je->id], $this->holders($this->report));
        $back = WorkflowAssignment::where('is_active', true)->sole();
        $this->assertSame(['primary', null], [$back->assigned_via, $back->original_user_id]);
        $this->assertSame('returned', WorkflowAssignment::find($back->previous_assignment_id)->end_reason);
        $this->assertSame(3, WorkflowAssignment::count()); // JE → stand-in → JE: full chain kept
    }

    #[Test]
    public function without_return_on_end_tasks_stay_with_the_stand_in(): void
    {
        $this->delegate($this->je, $this->standIn, 'all_pending', ['return_on_end' => 0])->assertRedirect();
        $this->actingAs($this->ae)->post("/delegations/{$this->delegation()->id}/end", ['reason' => 'Rejoined'])->assertRedirect();

        $this->assertSame([$this->standIn->id], $this->holders($this->report));
    }

    #[Test]
    public function scheduled_delegations_start_and_end_automatically(): void
    {
        $this->delegate($this->je, $this->standIn, 'all_pending', [
            'starts_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            'ends_at' => now()->addDays(3)->setTime(18, 0)->toDateTimeString(),
        ])->assertRedirect();
        $this->assertSame('scheduled', $this->delegation()->status);
        $this->assertSame([$this->je->id], $this->holders($this->report));

        $this->travelTo(now()->addDay()->setTime(9, 1));
        $this->artisan('rcd:delegations')->assertSuccessful();
        $this->assertSame('active', $this->delegation()->status);
        $this->assertSame([$this->standIn->id], $this->holders($this->report));

        $this->travelTo(now()->addDays(3));
        $this->artisan('rcd:delegations')->assertSuccessful();
        $this->assertSame('ended', $this->delegation()->status);
        $this->assertSame([$this->je->id], $this->holders($this->report));
    }

    #[Test]
    public function leave_chains_are_followed_for_new_tasks(): void
    {
        $third = $this->userByEmail('je010@rcd.test');
        $this->delegate($this->je, $this->standIn, 'new_only')->assertRedirect();
        $this->delegate($this->standIn, $third, 'new_only')->assertRedirect();

        $newReport = $this->fileReport(User::where('mobile', '9500000002')->first(), 'RCD-005', 9000);
        $this->assertSame([$third->id], $this->holders($newReport));
    }

    #[Test]
    public function invalid_delegations_are_refused(): void
    {
        $this->delegate($this->je, $this->je, 'all_pending')->assertSessionHasErrors('delegate_user_id');
        $this->delegate($this->je, $this->ae, 'all_pending')->assertSessionHasErrors('delegate_user_id'); // AE is not a JE
        $this->delegate($this->je, $this->standIn, 'all_pending', ['ends_at' => now()->subDay()->toDateTimeString()])->assertSessionHasErrors('ends_at');

        $this->delegate($this->je, $this->standIn, 'new_only')->assertRedirect();
        $this->delegate($this->je, $this->userByEmail('je010@rcd.test'), 'new_only')->assertSessionHasErrors('starts_at'); // overlap

        $this->delegate($this->userByEmail('je010@rcd.test'), $this->je, 'new_only')->assertSessionHasErrors('delegate_user_id'); // stand-in is away
    }

    #[Test]
    public function only_managers_create_delegations_and_officers_see_their_own(): void
    {
        $this->delegate($this->je, $this->standIn, 'new_only', by: $this->je)->assertForbidden();
        $this->delegate($this->je, $this->standIn, 'new_only')->assertRedirect();

        $this->actingAs($this->je)->get('/delegations')->assertOk()->assertSee($this->standIn->name);
        $this->actingAs($this->userByEmail('je005@rcd.test'))->get('/delegations')->assertOk()->assertDontSee($this->standIn->name);
        $this->actingAs($this->userByEmail('je005@rcd.test'))->get("/delegations/{$this->delegation()->id}")->assertForbidden();
        $this->actingAs($this->ae)->get('/delegations/create')->assertOk();
    }
}
