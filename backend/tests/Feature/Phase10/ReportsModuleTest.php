<?php

namespace Tests\Feature\Phase10;

use App\Domain\Analytics\AnalyticsService;
use App\Models\Contract;
use App\Models\ContractRoadSection;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\WorkflowHelper;

class ReportsModuleTest extends SeededTestCase
{
    use WorkflowHelper;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
        $citizen = User::where('mobile', '9500000001')->firstOrFail();
        $this->report = $this->fileReport($citizen, 'RCD-005', 5000);
        $r = $this->report->currentResponsibility;
        $this->act(User::find($r->je_user_id), $this->report, 'validate')->assertOk();
        $contractor = User::where('contractor_id', $r->contractor_id)->first();
        $this->act($contractor, $this->report, 'submit_repair', ['repair_description' => 'Patched', 'evidence' => $this->photos(2, 1)])->assertOk();
        $this->act(User::find($r->je_user_id), $this->report, 'reject', ['comment' => 'Edges not sealed properly', 'evidence' => $this->photos(1, 2)])->assertOk();
        $this->fileReport($citizen, 'RCD-005', 5300); // same 500 m band → recurring problem area
    }

    #[Test]
    public function every_report_type_renders_and_groups_correctly(): void
    {
        $ee = $this->userByEmail('ee.dn@rcd.test');
        $this->actingAs($ee)->get('/analytics')->assertOk()->assertSee('Rejection report');

        foreach (array_keys(AnalyticsService::TYPES) as $type) {
            $this->actingAs($ee)->get("/analytics/{$type}")->assertOk();
        }

        $this->actingAs($ee)->get('/analytics/monthly')->assertViewHas('table', fn ($t) => $t->rows[0]['reported'] === 2 && $t->rows[0]['rejections'] === 1);
        $this->actingAs($ee)->get('/analytics/road')->assertViewHas('table', fn ($t) => str_starts_with($t->rows[0]['group'], 'RCD-005') && $t->rows[0]['reported'] === 2);
        $this->actingAs($ee)->get('/analytics/je')->assertViewHas('table', fn ($t) => $t->rows[0]['reported'] === 2 && str_contains($t->rows[0]['group'], 'JE'));
        $this->actingAs($ee)->get('/analytics/rejections')->assertSee('Edges not sealed properly');
        $this->actingAs($ee)->get('/analytics/reopenings')->assertSee($this->report->report_no);
        $this->actingAs($ee)->get('/analytics/sla')->assertViewHas('table', fn ($t) => collect($t->rows)->firstWhere('stage', 'JE validation')['completed'] === 1);
        $this->actingAs($ee)->get('/analytics/unknown')->assertNotFound();
        $this->actingAs(User::where('mobile', '9500000001')->first())->get('/analytics')->assertForbidden();
    }

    #[Test]
    public function report_exports_carry_filters_and_respect_test_data_rules(): void
    {
        $admin = $this->userByEmail('admin@rcd.test');
        $csv = $this->actingAs($admin)->get('/analytics/rejections?export=csv&include_test=1&from=2026-01-01')->streamedContent();

        $this->assertStringContainsString('Rejection report', $csv);
        $this->assertStringContainsString('From: 01-Jan-2026', $csv);
        $this->assertStringContainsString('Edges not sealed properly', $csv);
        $this->assertStringNotContainsString('Edges not sealed properly', $this->actingAs($admin)->get('/analytics/rejections?export=csv')->streamedContent());
        $this->actingAs($admin)->get('/analytics/contractor?export=pdf&include_test=1')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function the_contract_completion_report_renders_and_exports(): void
    {
        $contract = Contract::find($this->report->currentResponsibility->contract_id);
        $ee = $this->userByEmail('ee.dn@rcd.test');
        $admin = $this->userByEmail('admin@rcd.test');

        $this->actingAs($ee)->get("/contracts/{$contract->id}/completion-report")->assertOk()
            ->assertSee('Final status')->assertSee('Maintenance period in progress')->assertSee($this->report->report_no)
            ->assertSee('does not award, rank or recommend');

        // Demo contracts are test data: an official export refuses them unless test data is included.
        $this->actingAs($admin)->get("/contracts/{$contract->id}/completion-report?export=pdf")->assertNotFound();
        $this->actingAs($admin)->get("/contracts/{$contract->id}/completion-report?export=pdf&include_test=1")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($admin)->get("/contracts/{$contract->id}/completion-report?export=xlsx&include_test=1")->assertOk();

        $expired = ContractRoadSection::where('road_id', $this->road('RCD-015')->id)->value('contract_id');
        $this->actingAs($ee)->get("/contracts/{$expired}/completion-report")->assertOk()->assertSee('Maintenance period ended on 31-Mar-2026');
    }

    #[Test]
    public function road_history_finds_recurring_problem_areas(): void
    {
        $road = $this->road('RCD-005');
        $je = $this->userByEmail('je001@rcd.test');

        $this->actingAs($je)->get("/roads/{$road->id}/history")->assertOk()
            ->assertSee('Recurring problem areas')->assertSee('km 5.000 – 5.500')
            ->assertViewHas('totals', fn ($t) => $t['reported'] === 2 && $t['repairs'] === 1);
        $this->actingAs($je)->get("/roads/{$road->id}/history?year=2020")->assertViewHas('totals', fn ($t) => $t['reported'] === 0);
    }

    #[Test]
    public function ended_maintenance_periods_announce_their_completion_report_once(): void
    {
        $contract = Contract::find($this->report->currentResponsibility->contract_id);
        $contract->update(['maintenance_end_date' => now()->subDays(2)->toDateString()]);
        $ee = User::find($this->report->currentResponsibility->ee_user_id);

        $this->artisan('rcd:contract-completions')->expectsOutput('Completion reports announced: 1')->assertSuccessful();
        $this->artisan('rcd:contract-completions')->expectsOutput('Completion reports announced: 0')->assertSuccessful();

        $note = $ee->notifications()->where('data->event', 'contract.completed')->sole();
        $this->assertStringContainsString($contract->contract_no, $note->data['message']);
        $this->assertSame("/contracts/{$contract->id}/completion-report", $note->data['url']);
        $this->assertTrue($this->userByEmail('admin@rcd.test')->notifications()->where('data->event', 'contract.completed')->exists());
    }
}
