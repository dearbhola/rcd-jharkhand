<?php

namespace Tests\Feature\Phase6;

use App\Domain\Masters\ResponsibilityService;
use App\Enums\RoleCode;
use App\Models\Report;
use App\Models\ReportLink;
use App\Models\ReportResponsibility;
use App\Models\User;
use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\WorkflowHelper;

class WorkflowRulesTest extends SeededTestCase
{
    use WorkflowHelper;

    private Report $report;

    private User $je;

    private User $ae;

    private User $ee;

    private User $contractor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
        $this->report = $this->fileReport($this->citizen());
        $r = $this->report->currentResponsibility;
        [$this->je, $this->ae, $this->ee] = [User::find($r->je_user_id), User::find($r->ae_user_id), User::find($r->ee_user_id)];
        $this->contractor = User::where('contractor_id', $r->contractor_id)->firstOrFail();
    }

    private function citizen(): User
    {
        return User::where('mobile', '9500000001')->firstOrFail();
    }

    /** Drive the report to a given review stage. */
    private function toStage(string $stage): void
    {
        $this->act($this->je, $this->report, 'validate')->assertOk();
        $this->act($this->contractor, $this->report, 'submit_repair', ['repair_description' => 'Patched', 'evidence' => $this->photos(2, 7)])->assertOk();
        if ($stage === 'JE_REVIEW') {
            return;
        }
        $this->act($this->je, $this->report, 'accept', ['evidence' => $this->photos(1, 8)])->assertOk();
        if ($stage === 'AE_REVIEW') {
            return;
        }
        $this->act($this->ae, $this->report, 'accept', ['evidence' => $this->photos(1, 9)])->assertOk();
    }

    #[Test]
    public function the_ae_can_reject_and_the_task_reopens_to_the_contractor(): void
    {
        $this->toStage('AE_REVIEW');

        $this->act($this->ae, $this->report, 'reject', ['comment' => 'Edges not sealed', 'evidence' => $this->photos(1, 10)])->assertOk();

        $this->assertSame('REOPENED', $this->report->refresh()->status);
        $attempt = $this->report->repairAttempts()->sole();
        $this->assertSame(['rejected', 'AE', 'Edges not sealed'], [$attempt->outcome, $attempt->rejected_stage, $attempt->rejection_reason]);
        $this->assertSame([$this->contractor->id], $this->holders($this->report));
    }

    #[Test]
    public function the_ee_rejection_needs_a_reason_and_restarts_repair_then_full_review(): void
    {
        $this->toStage('EE_APPROVAL');

        $this->act($this->ee, $this->report, 'reject', ['comment' => ''])->assertJsonValidationErrors('comment');
        $this->act($this->ee, $this->report, 'reject', ['comment' => 'Surface uneven; redo'])->assertOk();
        $this->assertSame('REOPENED', $this->report->refresh()->status);
        $this->assertSame('EE', $this->report->repairAttempts()->sole()->rejected_stage);

        // EE does not replace JE/AE inspection: the new attempt goes back to JE.
        $this->act($this->contractor, $this->report, 'submit_repair', ['repair_description' => 'Redone', 'evidence' => $this->photos(2, 11)])->assertOk();
        $this->assertSame('JE_REVIEW', $this->report->refresh()->status);
        $this->assertSame([$this->je->id], $this->holders($this->report));
    }

    #[Test]
    public function field_review_requires_being_at_the_site_with_evidence(): void
    {
        $this->toStage('JE_REVIEW');

        $this->act($this->je, $this->report, 'accept', ['evidence' => $this->photos(1, 12)], offsetM: 400)
            ->assertJsonValidationErrors('location')
            ->assertJsonPath('errors.location.0', fn ($m) => str_contains($m, 'You must be within 50 m'));
        $this->act($this->je, $this->report, 'accept', [])->assertJsonValidationErrors('evidence');
        $this->act($this->je, $this->report, 'accept', ['accuracy' => 90, 'evidence' => $this->photos(1, 13)])->assertJsonValidationErrors('location');
        $this->assertSame('JE_REVIEW', $this->report->refresh()->status);
    }

    #[Test]
    public function repairs_also_require_presence_and_evidence(): void
    {
        $this->act($this->je, $this->report, 'validate')->assertOk();

        $this->act($this->contractor, $this->report, 'submit_repair', ['repair_description' => 'Done', 'evidence' => $this->photos(2, 14)], offsetM: 500)->assertJsonValidationErrors('location');
        $this->act($this->contractor, $this->report, 'submit_repair', ['repair_description' => 'Done', 'evidence' => $this->photos(1, 15)])->assertJsonValidationErrors('evidence');
        $this->act($this->contractor, $this->report, 'submit_repair', ['evidence' => $this->photos(2, 16)])->assertJsonValidationErrors('repair_description');
        $this->assertSame(0, $this->report->repairAttempts()->count());
    }

    #[Test]
    public function only_the_current_holder_with_the_right_role_may_act(): void
    {
        $this->toStage('JE_REVIEW');
        $otherJe = $this->userByEmail('je008@rcd.test');
        $otherContractor = User::whereNotNull('contractor_id')->where('contractor_id', '!=', $this->contractor->contractor_id)->first();

        $this->act($this->ae, $this->report, 'accept', ['evidence' => $this->photos(1, 17)])->assertForbidden(); // AE acts after JE
        $this->act($otherJe, $this->report, 'accept', ['evidence' => $this->photos(1, 18)])->assertForbidden();
        $this->act($this->contractor, $this->report, 'accept', ['evidence' => $this->photos(1, 19)])->assertForbidden();
        $this->act($otherContractor, $this->report, 'submit_repair', [])->assertForbidden();
        $this->act($this->citizen(), $this->report, 'accept', [])->assertForbidden();
        $this->act($this->ee, $this->report, 'approve', [])->assertStatus(409); // not available at this stage
    }

    #[Test]
    public function a_stale_version_is_refused_so_two_people_cannot_process_the_same_step(): void
    {
        $this->toStage('JE_REVIEW');
        $seen = $this->version($this->report);

        $this->act($this->je, $this->report, 'accept', ['evidence' => $this->photos(1, 20)], version: $seen)->assertOk();
        $this->act($this->je, $this->report, 'reject', ['comment' => 'Second click', 'evidence' => $this->photos(1, 21)], version: $seen)
            ->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'already processed'));

        $this->assertSame('AE_REVIEW', $this->report->refresh()->status);
        $this->assertSame('accepted_je', $this->report->repairAttempts()->sole()->outcome);
    }

    #[Test]
    public function an_invalid_report_is_closed_with_a_mandatory_reason(): void
    {
        $this->act($this->je, $this->report, 'invalidate', ['comment' => 'no'])->assertJsonValidationErrors('comment');
        $this->act($this->je, $this->report, 'invalidate', ['comment' => 'Not an RCD road defect — private driveway'])->assertOk();

        $this->report->refresh();
        $this->assertSame('INVALID_CLOSED', $this->report->status);
        $this->assertNotNull($this->report->closed_at);
        $this->assertSame([], $this->holders($this->report));
        $this->assertTrue($this->citizen()->notifications()->where('data->status', 'INVALID_CLOSED')->exists());
        $this->assertSame('invalid', $this->report->inspections()->sole()->decision);
    }

    #[Test]
    public function a_duplicate_is_merged_into_the_original_and_both_are_kept(): void
    {
        $duplicate = $this->fileReport(User::where('mobile', '9500000002')->first(), 'RCD-005', 3005);

        $this->act($this->je, $duplicate, 'merge_duplicate', ['comment' => 'Same pothole', 'duplicate_of' => $duplicate->id])->assertJsonValidationErrors('duplicate_of');
        $this->act($this->je, $duplicate, 'merge_duplicate', ['comment' => 'Same pothole', 'duplicate_of' => $this->report->id])->assertOk();

        $this->assertSame('MERGED_DUPLICATE', $duplicate->refresh()->status);
        $this->assertTrue(ReportLink::where('report_id', $duplicate->id)->where('linked_report_id', $this->report->id)->where('link_type', 'duplicate_of')->exists());
        $this->assertSame('PENDING_VALIDATION', $this->report->refresh()->status);
        $this->assertCount(2, $duplicate->evidences);
    }

    #[Test]
    public function officer_reports_skip_validation_and_go_straight_to_the_contractor(): void
    {
        foreach ([$this->je, $this->ae] as $officer) {
            $report = $this->fileReport($officer, 'RCD-005', $officer->is($this->je) ? 9000 : 11000);

            $this->assertSame('ASSIGNED', $report->refresh()->status);
            $this->assertSame([$this->contractor->id], $this->holders($report));
            $this->assertSame(0, $report->inspections()->count()); // system validation, no fake inspection
        }
    }

    #[Test]
    public function the_department_flow_handles_roads_without_active_maintenance(): void
    {
        $report = $this->fileReport($this->citizen(), 'RCD-015', 2000);
        $je = User::find($report->currentResponsibility->je_user_id);
        $instance = WorkflowInstance::where('report_id', $report->id)->with('definition')->sole();

        $this->assertSame('DEPARTMENT', $instance->definition->code);
        $this->act($je, $report, 'validate')->assertOk();
        $this->assertSame('DEPARTMENT_PENDING', $report->refresh()->status);
        $this->assertSame([$je->id], $this->holders($report));

        $this->act($je, $report, 'close', ['comment' => ''])->assertJsonValidationErrors('comment');
        $this->act($je, $report, 'close', ['comment' => 'Repaired departmentally under work order 45'])->assertOk();
        $this->assertSame('CLOSED', $report->refresh()->status);
    }

    #[Test]
    public function work_moves_to_the_department_when_maintenance_expires_mid_workflow(): void
    {
        $report = $this->fileReport($this->citizen(), 'RCD-006', 2000); // maintenance ends 30-Sep-2027
        $r = $report->currentResponsibility;
        $je = User::find($r->je_user_id);
        $contractor = User::where('contractor_id', $r->contractor_id)->firstOrFail();
        $this->act($je, $report, 'validate')->assertOk();
        $this->act($contractor, $report, 'submit_repair', ['repair_description' => 'Patched', 'evidence' => $this->photos(2, 30)])->assertOk();

        $this->travelTo(now()->setDate(2027, 10, 15));
        $je->forceFill(['password_changed_at' => now()])->saveQuietly(); // a year on, the 90-day password policy would otherwise intervene
        $this->act($je, $report, 'reject', ['comment' => 'Failed again', 'evidence' => $this->photos(1, 31)])->assertOk();

        $report->refresh();
        $this->assertSame('DEPARTMENT_PENDING', $report->status);
        $this->assertSame(['contractor', 'department'], ReportResponsibility::where('report_id', $report->id)->orderBy('id')->pluck('route')->all());
        $this->assertSame('maintenance_expired', $report->currentResponsibility->reason);
        $this->assertSame(['superseded', 'active'], WorkflowInstance::where('report_id', $report->id)->orderBy('id')->pluck('status')->all());
        $this->assertSame([$je->id], $this->holders($report));
        $this->assertSame('rejected', $report->repairAttempts()->sole()->outcome); // history kept
    }

    #[Test]
    public function the_next_review_goes_to_the_currently_mapped_je_after_a_transfer(): void
    {
        $this->act($this->je, $this->report, 'validate')->assertOk();
        $newJe = $this->userByEmail('je009@rcd.test');
        app(ResponsibilityService::class)->assign($this->userByEmail('admin@rcd.test'), 'road_section', [$this->report->road_section_id], RoleCode::JE, $newJe, now()->toDateString());

        $this->act($this->contractor, $this->report, 'submit_repair', ['repair_description' => 'Patched', 'evidence' => $this->photos(2, 40)])->assertOk();

        $this->assertSame([$newJe->id], $this->holders($this->report));
        $this->assertSame($this->je->id, $this->report->currentResponsibility->je_user_id); // historical snapshot unchanged
    }

    #[Test]
    public function the_report_page_offers_only_the_holders_actions_and_the_task_list_shows_the_task(): void
    {
        $this->actingAs($this->je)->get("/reports/{$this->report->id}")->assertOk()->assertSee('Validate report')->assertSee('Close as invalid');
        $this->actingAs($this->ae)->get("/reports/{$this->report->id}")->assertOk()->assertDontSee('Validate report');
        $this->actingAs($this->citizen())->get("/reports/{$this->report->id}")->assertOk()->assertDontSee('Your action');
        $this->actingAs($this->je)->get('/tasks')->assertOk()->assertSee($this->report->report_no);
        $this->actingAs($this->ae)->get('/tasks')->assertOk()->assertDontSee($this->report->report_no);
    }
}
