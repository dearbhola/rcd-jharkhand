<?php

namespace Tests\Feature\Phase6;

use App\Models\AuditLog;
use App\Models\Report;
use App\Models\SlaInstance;
use App\Models\User;
use App\Models\WorkflowAction;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\WorkflowHelper;

/**
 * Requirement §61: the end-to-end demo scenario.
 */
class DemoScenarioTest extends SeededTestCase
{
    use WorkflowHelper;

    #[Test]
    public function citizen_report_to_ee_approval_with_one_rejection(): void
    {
        Storage::fake('evidence');

        // Citizen reports pothole → GPS detects road → section → JE/AE/EE → active maintenance contract.
        $citizen = User::where('mobile', '9500000001')->firstOrFail();
        $report = $this->fileReport($citizen, 'RCD-005', 7400);
        $resp = $report->currentResponsibility;
        $je = User::find($resp->je_user_id);
        $ae = User::find($resp->ae_user_id);
        $ee = User::find($resp->ee_user_id);
        $contractor = User::where('contractor_id', $resp->contractor_id)->firstOrFail();

        $this->assertSame('contractor', $resp->route);
        $this->assertSame('PENDING_VALIDATION', $report->status);
        $this->assertSame([$je->id], $this->holders($report));

        // JE validates → contractor receives the task.
        $this->act($je, $report, 'validate', ['comment' => 'Genuine pothole'])->assertOk();
        $report->refresh();
        $this->assertSame('ASSIGNED', $report->status);
        $this->assertSame([$contractor->id], $this->holders($report));
        $this->assertTrue($contractor->notifications()->exists());

        // Contractor starts, repairs, captures GPS/photo/video.
        $this->act($contractor, $report, 'acknowledge')->assertOk();
        $this->act($contractor, $report, 'submit_repair', [
            'repair_description' => 'Cut, cleaned, tack coat, BC laid and compacted',
            'evidence' => [...$this->photos(2, 1), $this->video()],
        ])->assertOk();
        $this->assertSame('JE_REVIEW', $report->refresh()->status);
        $this->assertSame([$je->id], $this->holders($report));

        // JE visits, captures inspection evidence, rejects with a mandatory reason.
        $this->act($je, $report, 'reject', ['evidence' => $this->photos(1, 2)])->assertJsonValidationErrors('comment');
        $this->act($je, $report, 'reject', ['comment' => 'Repair not completed according to required condition.', 'evidence' => $this->photos(1, 2)])->assertOk();
        $report->refresh();
        $this->assertSame('REOPENED', $report->status);
        $this->assertSame([$contractor->id], $this->holders($report));

        // Contractor repairs again.
        $this->act($contractor, $report, 'submit_repair', ['repair_description' => 'Re-laid with proper compaction', 'evidence' => $this->photos(2, 3)])->assertOk();

        // JE accepts, AE accepts → forwarded to EE.
        $this->act($je, $report, 'accept', ['evidence' => $this->photos(1, 4)])->assertOk();
        $this->assertSame('AE_REVIEW', $report->refresh()->status);
        $this->act($ae, $report, 'accept', ['evidence' => $this->photos(1, 5)])->assertOk();
        $this->assertSame('EE_APPROVAL', $report->refresh()->status);
        $this->assertSame([$ee->id], $this->holders($report));

        // EE approves → CLOSED.
        $this->act($ee, $report, 'approve', ['comment' => 'Approved'])->assertOk();
        $report->refresh();
        $this->assertSame('CLOSED', $report->status);
        $this->assertNotNull($report->closed_at);
        $this->assertSame([], $this->holders($report));

        // Every repair attempt preserved with its own outcome, evidence and reviewer.
        $attempts = $report->repairAttempts()->with('evidences', 'inspections')->get();
        $this->assertCount(2, $attempts);
        [$first, $second] = $attempts;
        $this->assertSame(['rejected', 'JE', 'Repair not completed according to required condition.', $je->id],
            [$first->outcome, $first->rejected_stage, $first->rejection_reason, $first->rejected_by]);
        $this->assertCount(3, $first->evidences); // 2 photos + video
        $this->assertSame('approved', $second->outcome);
        $this->assertSame(['JE_REVIEW', 'AE_REVIEW', 'EE_APPROVAL'], $second->inspections->pluck('stage')->all());
        $this->assertTrue($first->inspections->first()->location_verified);

        // Complete history: 7 transitions, all audited; SLA timers all closed.
        $actions = WorkflowAction::whereHas('instance', fn ($q) => $q->where('report_id', $report->id))->orderBy('id')->pluck('action_code')->all();
        $this->assertSame(['validate', 'acknowledge', 'submit_repair', 'reject', 'submit_repair', 'accept', 'accept', 'approve'], $actions);
        $audited = AuditLog::where('auditable_type', 'report')->where('auditable_id', $report->id)->pluck('action')->all();
        foreach (['report.created', 'workflow.validate', 'workflow.reject', 'workflow.approve'] as $expected) {
            $this->assertContains($expected, $audited);
        }
        $this->assertSame(0, SlaInstance::where('report_id', $report->id)->whereNull('completed_at')->count());
        $this->assertGreaterThan(5, SlaInstance::where('report_id', $report->id)->count());

        // The reporter was kept informed and can see the closed report.
        $this->assertTrue($citizen->notifications()->where('data->status', 'CLOSED')->exists());
        $this->actingAs($citizen)->get("/reports/{$report->id}")->assertOk()->assertSee('Attempt 2')->assertSee('Repair not completed');
    }
}
