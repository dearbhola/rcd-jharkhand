<?php

namespace Tests\Feature\Phase8;

use App\Domain\Sla\SlaService;
use App\Models\AuditLog;
use App\Models\Escalation;
use App\Models\EscalationRule;
use App\Models\IssueCategory;
use App\Models\Report;
use App\Models\Severity;
use App\Models\SlaInstance;
use App\Models\SlaRule;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\WorkflowHelper;

class EscalationTest extends SeededTestCase
{
    use WorkflowHelper;

    private Report $report;

    private User $je;

    private User $ae;

    private User $ee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
        $this->report = $this->fileReport(User::where('mobile', '9500000001')->firstOrFail()); // HIGH → validation SLA 12 h
        $r = $this->report->currentResponsibility;
        [$this->je, $this->ae, $this->ee] = [User::find($r->je_user_id), User::find($r->ae_user_id), User::find($r->ee_user_id)];
    }

    private function timer(): SlaInstance
    {
        return SlaInstance::where('report_id', $this->report->id)->whereNull('completed_at')->sole();
    }

    private function scan(): void
    {
        $this->artisan('rcd:sla-scan')->assertSuccessful();
    }

    private function events(User $user): array
    {
        return $user->notifications()->pluck('data')->pluck('event')->all();
    }

    #[Test]
    public function reminder_then_breach_to_ae_then_escalation_to_ee_each_exactly_once(): void
    {
        $timer = $this->timer();
        $this->assertSame('VALIDATION', $timer->stage);
        $this->assertEquals(12, $timer->hours);

        // 3 h 59 m before due: nothing; 4 h before: reminder to the JE.
        $this->travelTo($timer->due_at->copy()->subHours(5));
        $this->scan();
        $this->assertNotContains('sla.approaching', $this->events($this->je));
        $this->travelTo($timer->due_at->copy()->subHours(4)->addMinute());
        $this->scan();
        $this->scan();
        $this->assertSame(1, collect($this->events($this->je))->filter(fn ($e) => $e === 'sla.approaching')->count());

        // Breach: recorded, JE + AE notified.
        $this->travelTo($timer->due_at->copy()->addMinutes(10));
        $this->scan();
        $this->assertEquals($timer->due_at, $timer->fresh()->breached_at);
        $this->assertContains('sla.breached', $this->events($this->je));
        $this->assertContains('sla.breached', $this->events($this->ae));
        $this->assertNotContains('sla.escalated', $this->events($this->ee));

        // 24 h later: escalated to the EE.
        $this->travelTo($timer->due_at->copy()->addHours(24)->addMinute());
        $this->je->forceFill(['password_changed_at' => now()])->saveQuietly();
        $this->scan();
        $this->scan();
        $this->assertSame(1, collect($this->events($this->ee))->filter(fn ($e) => $e === 'sla.escalated')->count());
        $this->assertSame(3, Escalation::where('sla_instance_id', $timer->id)->count());
        $this->assertTrue(AuditLog::where('action', 'sla.escalate')->where('auditable_id', $this->report->id)->exists());

        // The report page shows the overdue timer and its escalations.
        $this->actingAs($this->ee)->get("/reports/{$this->report->id}")->assertOk()->assertSee('Overdue')->assertSee('Escalated to EE');
    }

    #[Test]
    public function completed_stages_are_not_escalated_and_late_completion_is_recorded(): void
    {
        $timer = $this->timer();
        $this->travelTo($timer->due_at->copy()->subHour());
        $this->act($this->je, $this->report, 'validate')->assertOk(); // on time
        $this->assertNull($timer->fresh()->breached_at);
        $this->assertNotNull($timer->fresh()->completed_at);

        $response = SlaInstance::where('report_id', $this->report->id)->whereNull('completed_at')->sole(); // contractor RESPONSE
        $this->assertSame('RESPONSE', $response->stage);
        $this->travelTo($timer->due_at->copy()->addDays(2));
        $this->scan();
        $this->assertSame(0, Escalation::where('sla_instance_id', $timer->id)->where('fired_at', '>', $timer->due_at)->count());

        $contractor = User::where('contractor_id', $this->report->currentResponsibility->contractor_id)->first();
        $this->act($contractor, $this->report, 'acknowledge')->assertOk(); // late
        $this->assertNotNull($response->fresh()->breached_at);
    }

    #[Test]
    public function reminders_are_skipped_when_the_sla_is_shorter_than_the_lead_time(): void
    {
        $critical = Severity::where('code', 'CRITICAL')->value('id');
        $report = $this->fileReport(User::where('mobile', '9500000002')->first(), 'RCD-005', 9000, ['severity_id' => $critical]);
        $timer = SlaInstance::where('report_id', $report->id)->sole();
        $this->assertEquals(6, $timer->hours); // CRITICAL validation

        // Lead time 4 h < 6 h → reminder at due-4h; set a rule with 8 h lead which must not fire at start.
        EscalationRule::create(['trigger' => 'before_due', 'offset_hours' => 8, 'level' => 0, 'action' => 'remind', 'notify_assignee' => true, 'is_active' => true]);
        $this->travelTo($timer->started_at->copy()->addMinutes(5));
        $this->scan();
        $this->assertSame(0, Escalation::where('sla_instance_id', $timer->id)->count());
    }

    #[Test]
    public function escalations_reach_the_stand_in_when_the_ae_is_on_leave(): void
    {
        $standIn = $this->userByEmail('ae.sdn2@rcd.test');
        $this->actingAs($this->ee)->post('/delegations', [
            'primary_user_id' => $this->ae->id, 'delegate_user_id' => $standIn->id, 'role_code' => 'AE',
            'starts_at' => now()->subMinute()->toDateTimeString(), 'ends_at' => now()->addDays(10)->toDateTimeString(),
            'reason' => 'Training', 'transfer_mode' => 'new_only', 'return_on_end' => 1,
        ])->assertRedirect();

        $this->travelTo($this->timer()->due_at->copy()->addMinutes(10));
        $this->scan();

        $this->assertContains('sla.breached', $this->events($standIn));
        $this->assertNotContains('sla.breached', $this->events($this->ae));
    }

    #[Test]
    public function the_most_specific_sla_rule_wins(): void
    {
        $pothole = IssueCategory::where('code', 'POTHOLE')->firstOrFail();
        $high = Severity::where('code', 'HIGH')->value('id');
        SlaRule::create(['stage' => 'VALIDATION', 'asset_type_id' => $pothole->asset_type_id, 'issue_category_id' => $pothole->id, 'hours' => 3]);

        $rule = app(SlaService::class)->ruleFor($this->report, 'VALIDATION');
        $this->assertEquals(3, $rule->hours);
        $this->assertSame($high, $this->report->severity_id); // the HIGH severity rule (12 h) loses to the category rule
    }
}
