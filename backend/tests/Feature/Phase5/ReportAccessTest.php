<?php

namespace Tests\Feature\Phase5;

use App\Models\Evidence;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\ReportsHelper;

class ReportAccessTest extends SeededTestCase
{
    use ReportsHelper;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
        $this->actingAs($this->citizen())->post('/reports', $this->reportPayload($this->road('RCD-005'), 3000), ['Accept' => 'application/json'])->assertCreated();
        $this->report = Report::sole();
    }

    private function citizen(): User
    {
        return User::where('mobile', '9500000001')->firstOrFail();
    }

    #[Test]
    public function who_can_see_a_report(): void
    {
        $resp = $this->report->currentResponsibility;
        $mappedJe = User::find($resp->je_user_id);
        $contractorUser = User::where('contractor_id', $resp->contractor_id)->firstOrFail();
        $otherContractor = User::whereNotNull('contractor_id')->where('contractor_id', '!=', $resp->contractor_id)->firstOrFail();
        $url = "/reports/{$this->report->id}";

        $this->actingAs($this->citizen())->get($url)->assertOk()->assertSee($this->report->report_no);
        $this->actingAs(User::where('mobile', '9500000002')->first())->get($url)->assertForbidden();
        $this->actingAs($mappedJe)->get($url)->assertOk();
        $this->actingAs($this->userByEmail('je008@rcd.test'))->get($url)->assertForbidden(); // other sub-division
        $this->actingAs($this->userByEmail('admin@rcd.test'))->get($url)->assertOk();

        // Contractors only see validated reports routed to their firm.
        $this->actingAs($contractorUser)->get($url)->assertForbidden();
        $this->report->update(['status' => 'ASSIGNED']);
        $this->actingAs($contractorUser)->get($url)->assertOk();
        $this->actingAs($otherContractor)->get($url)->assertForbidden();
    }

    #[Test]
    public function lists_only_contain_visible_reports(): void
    {
        $this->actingAs($this->citizen())->get('/reports')->assertOk()->assertSee($this->report->report_no);
        $this->actingAs(User::where('mobile', '9500000002')->first())->get('/reports')->assertOk()->assertDontSee($this->report->report_no);
    }

    #[Test]
    public function evidence_is_served_only_to_authorised_viewers_and_originals_need_extra_permission(): void
    {
        $ev = Evidence::first();
        $mappedJe = User::find($this->report->currentResponsibility->je_user_id);
        $ee = User::find($this->report->currentResponsibility->ee_user_id);

        $this->actingAs($this->citizen())->get("/evidence/{$ev->id}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($this->citizen())->get("/evidence/{$ev->id}/thumbnail")->assertOk();
        $this->actingAs(User::where('mobile', '9500000002')->first())->get("/evidence/{$ev->id}")->assertForbidden();

        $this->actingAs($mappedJe)->get("/evidence/{$ev->id}/original")->assertForbidden();
        $this->actingAs($ee)->get("/evidence/{$ev->id}/original")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'evidence.original_downloaded', 'user_id' => $ee->id]);
    }

    #[Test]
    public function the_map_shows_reports_and_open_issue_counts(): void
    {
        $je = User::find($this->report->currentResponsibility->je_user_id);

        $features = $this->actingAs($je)->getJson('/map/reports.json?bbox=84,22,88,25')->assertOk()->json('features');
        $this->assertSame([$this->report->report_no], array_column(array_column($features, 'properties'), 'report_no'));

        $this->actingAs(User::where('mobile', '9500000002')->first());
        $this->assertSame([], $this->getJson('/map/reports.json?bbox=84,22,88,25')->json('features') ?? []);

        $this->actingAs($je)->getJson("/map/sections/{$this->report->road_section_id}.json")->assertJsonPath('open_issues', 1);
    }

    #[Test]
    public function the_report_page_shows_location_evidence_and_responsibility(): void
    {
        $this->actingAs($this->userByEmail('admin@rcd.test'))->get("/reports/{$this->report->id}")
            ->assertOk()
            ->assertSee('RCD-005')
            ->assertSee(km($this->report->chainage_m, true))
            ->assertSee('Contractor maintenance')
            ->assertSee(route('evidence.thumbnail', Evidence::first()));
    }
}
