<?php

namespace Tests\Feature\Phase5;

use App\Domain\Evidence\MediaInspector;
use App\Domain\Gis\LineString;
use App\Domain\Gis\LocationResolver;
use App\Domain\Gis\RoadGeometryService;
use App\Domain\Masters\RoadSectionService;
use App\Events\ReportSubmitted;
use App\Jobs\ProcessEvidence;
use App\Models\Asset;
use App\Models\Evidence;
use App\Models\Report;
use App\Models\Road;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\ReportsHelper;

class ReportSubmissionTest extends SeededTestCase
{
    use ReportsHelper;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(11, 42, 18));
    }

    private function citizen(): User
    {
        return User::where('mobile', '9500000001')->firstOrFail();
    }

    private function submit(User $user, array $payload)
    {
        return $this->actingAs($user)->post('/reports', $payload, ['Accept' => 'application/json']);
    }

    #[Test]
    public function a_citizen_report_is_resolved_numbered_snapshotted_and_its_evidence_processed(): void
    {
        Event::fake([ReportSubmitted::class]);
        $road = $this->road('RCD-005');
        $payload = $this->reportPayload($road, 12345, ['road_id' => $this->road('RCD-001')->id, 'chainage_m' => 1]); // client lies: ignored

        $this->submit($this->citizen(), $payload)->assertCreated()->assertJsonPath('report_no', 'RCD/2026-27/000001');

        $report = Report::sole();
        $this->assertSame($road->id, $report->road_id);
        $this->assertSame('S02', $report->section->code);
        $this->assertEqualsWithDelta(12345, $report->chainage_m, 5);
        $this->assertSame('PENDING_VALIDATION', $report->status);
        $this->assertSame('CITIZEN', $report->reporter_role_code);
        $this->assertNotNull($report->finalized_at);

        $resp = $report->currentResponsibility;
        $this->assertSame('contractor', $resp->route);
        $this->assertNotNull($resp->contractor_id);
        $this->assertNotNull($resp->je_user_id);
        $this->assertNotNull($resp->ee_user_id);

        $this->assertCount(2, $report->evidences);
        foreach ($report->evidences as $ev) {
            Storage::disk('evidence')->assertExists([$ev->original_path, $ev->display_path, $ev->thumbnail_path]);
            $this->assertSame('done', $ev->processing_status);
            $this->assertSame(hash('sha256', Storage::disk('evidence')->get($ev->original_path)), $ev->sha256); // original untouched
            $this->assertNotSame($ev->sha256, hash('sha256', Storage::disk('evidence')->get($ev->display_path)));
            $this->assertLessThanOrEqual(1920, max($ev->width, $ev->height));
            $this->assertEquals($report->latitude, $ev->latitude);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'report.created', 'auditable_id' => $report->id]);
        Event::assertDispatched(ReportSubmitted::class, fn ($e) => $e->report->is($report));
    }

    #[Test]
    public function the_watermark_carries_the_required_facts(): void
    {
        $this->submit($this->citizen(), $this->reportPayload($this->road('RCD-005'), 14250))->assertCreated();
        $lines = implode("\n", ProcessEvidence::watermarkLines(Evidence::first()));

        foreach (['RCD ROAD MONITORING', 'Date: 02-Oct-2026', 'Time: 11:40:18 IST', 'Lat: ', 'Long: ', 'Road: RCD-005', 'Chainage: 14.2'] as $expected) {
            $this->assertStringContainsString($expected, $lines);
        }
    }

    #[Test]
    public function reports_away_from_any_road_are_refused(): void
    {
        $payload = $this->reportPayload($this->road('RCD-005'), 3000, ['offset' => 2500]);
        $this->assertNull(app(LocationResolver::class)->nearest($payload['latitude'], $payload['longitude'], 50), 'fixture point must be off-network');

        $this->submit($this->citizen(), $payload)->assertUnprocessable()
            ->assertJsonPath('errors.location.0', 'You are not currently within the permitted reporting area.');
        $this->assertSame(0, Report::count());
        $this->assertSame([], Storage::disk('evidence')->allFiles());
    }

    #[Test]
    public function the_radius_is_configurable(): void
    {
        app(Settings::class)->set('report.location_radius_m', 500);

        $this->submit($this->citizen(), $this->reportPayload($this->road('RCD-005'), 3000, ['offset' => 400]))->assertCreated();
    }

    #[Test]
    public function poor_gps_accuracy_and_bad_timestamps_are_refused(): void
    {
        $road = $this->road('RCD-005');

        $this->submit($this->citizen(), $this->reportPayload($road, 3000, ['accuracy' => 80]))->assertUnprocessable()->assertJsonValidationErrors('accuracy');
        $this->submit($this->citizen(), $this->reportPayload($road, 3000, ['captured_at' => now()->addHour()->toIso8601String()]))->assertJsonValidationErrors('captured_at');
        $this->submit($this->citizen(), $this->reportPayload($road, 3000, ['captured_at' => now()->subDays(4)->toIso8601String()]))->assertJsonValidationErrors('captured_at');
        $this->assertSame(0, Report::count());
    }

    #[Test]
    public function evidence_limits_types_and_duplicates_are_enforced(): void
    {
        $road = $this->road('RCD-005');
        $fakePdf = UploadedFile::fake()->createWithContent('photo.jpg', "%PDF-1.4\n%fake\n");
        $same = $this->photo('same.jpg');

        $this->submit($this->citizen(), $this->reportPayload($road, 3000, ['evidence' => []]))->assertJsonValidationErrors('evidence');
        $this->submit($this->citizen(), $this->reportPayload($road, 3000, ['evidence' => array_map(fn ($i) => $this->photo("p{$i}.jpg", 800 + $i), range(1, 6))]))
            ->assertJsonPath('errors.evidence.0', 'At most 5 photo(s) allowed.');
        $this->submit($this->citizen(), $this->reportPayload($road, 3000, ['evidence' => [$fakePdf]]))->assertJsonValidationErrors('evidence.0');
        $this->submit($this->citizen(), $this->reportPayload($road, 3000, ['evidence' => [$same, $same]]))
            ->assertJsonPath('errors', fn ($errors) => str_contains(json_encode($errors), 'attached twice'));

        app(Settings::class)->set('evidence.max_image_kb', 1);
        $this->submit($this->citizen(), $this->reportPayload($road, 3000))->assertJsonValidationErrors('evidence.0');

        $this->assertSame(0, Report::count());
    }

    #[Test]
    public function a_video_is_accepted_and_flagged_when_it_cannot_be_verified(): void
    {
        $this->submit($this->citizen(), $this->reportPayload($this->road('RCD-005'), 3000, ['evidence' => [$this->photo(), $this->video()]]))->assertCreated();

        $video = Evidence::where('kind', 'video')->sole();
        $this->assertSame('video/mp4', $video->mime_type);
        if (! app(MediaInspector::class)->ffprobeAvailable()) {
            $this->assertContains('duration_unverified', $video->flags);
            $this->assertContains('video_not_processed', $video->fresh()->flags);
        }
    }

    #[Test]
    public function submission_is_idempotent_on_the_client_uuid(): void
    {
        $payload = $this->reportPayload($this->road('RCD-005'), 3000);

        $this->submit($this->citizen(), $payload)->assertCreated();
        $payload['evidence'] = [$this->photo('retry1.jpg'), $this->photo('retry2.jpg')];
        $this->submit($this->citizen(), $payload)->assertOk()->assertJsonPath('report_no', Report::sole()->report_no);
        $this->assertSame(1, Report::count());
        $this->assertSame(2, Evidence::count());

        $other = User::where('mobile', '9500000002')->firstOrFail();
        $this->submit($other, $payload)->assertJsonValidationErrors('client_uuid');
    }

    #[Test]
    public function asset_reports_need_the_asset_to_be_at_the_location(): void
    {
        $bridge = Asset::where('code', 'RCD-005-BRIDGE-01')->firstOrFail();
        $road = $bridge->road;
        $bridgeCategory = $this->category('BRIDGE', 'DECK_DAMAGE');
        $base = ['asset_type_id' => $bridgeCategory->asset_type_id, 'issue_category_id' => $bridgeCategory->id];

        $farFromBridge = $bridge->chainage_m > 5000 ? 1000 : $bridge->chainage_m + 4000;
        $this->submit($this->citizen(), $this->reportPayload($road, $farFromBridge, $base))->assertJsonValidationErrors('asset_type_id');

        $this->submit($this->citizen(), $this->reportPayload($road, $bridge->chainage_m, [...$base, 'offset' => 2]))->assertCreated();
        $this->assertSame($bridge->id, Report::sole()->asset_id);
    }

    #[Test]
    public function the_category_must_belong_to_the_asset_type(): void
    {
        $road = $this->road('RCD-005');
        $bridgeCategory = $this->category('BRIDGE', 'DECK_DAMAGE');

        $this->submit($this->citizen(), $this->reportPayload($road, 3000, ['issue_category_id' => $bridgeCategory->id]))->assertJsonValidationErrors('issue_category_id');
    }

    #[Test]
    public function only_authorised_users_may_override_location_and_such_reports_are_test_data(): void
    {
        $road = $this->road('RCD-005');
        $farAway = ['offset' => 2000];

        $this->submit($this->citizen(), $this->reportPayload($road, 3000, [...$farAway, 'location_override' => 1]))->assertJsonValidationErrors('location');

        $this->submit($this->userByEmail('superadmin@rcd.test'), $this->reportPayload($road, 3000, [...$farAway, 'location_override' => 1]))->assertCreated();
        $report = Report::sole();
        $this->assertTrue($report->is_test);
        $this->assertTrue($report->location_override);
        $this->assertContains('location_override', $report->location_flags);

        app(Settings::class)->set('location.test_override_enabled', false);
        $this->submit($this->userByEmail('superadmin@rcd.test'), $this->reportPayload($road, 3000, [...$farAway, 'location_override' => 1]))->assertJsonValidationErrors('location');
    }

    #[Test]
    public function reports_on_production_roads_are_production_data(): void
    {
        $road = Road::create(['code' => 'LIVE-1', 'name' => 'Live road', 'division_id' => $this->road('RCD-001')->division_id,
            'start_chainage_m' => 0, 'end_chainage_m' => 5000, 'length_m' => 5000, 'status' => 'active']);
        app(RoadGeometryService::class)->saveRoadGeometry($road, new LineString([[85.9, 23.9], [85.9, 23.9 + 5000 / 111195]]));
        app(RoadSectionService::class)->create($road->fresh(), ['code' => 'S01', 'division_id' => $road->division_id, 'start_chainage_m' => 0, 'end_chainage_m' => 5000, 'status' => 'active']);

        $this->submit($this->citizen(), $this->reportPayload($road->fresh('currentGeometry'), 2000))->assertCreated();

        $report = Report::sole();
        $this->assertFalse($report->is_test);
        $this->assertContains('responsibility_gap', $report->location_flags); // no JE/AE/EE mapped yet
        $this->assertSame('department', $report->currentResponsibility->route);
    }

    #[Test]
    public function nearby_reports_of_the_same_category_are_flagged_as_possible_duplicates(): void
    {
        $road = $this->road('RCD-005');
        $this->submit($this->citizen(), $this->reportPayload($road, 3000))->assertCreated();
        $this->submit($this->citizen(), $this->reportPayload($road, 3010))->assertCreated();
        $crack = $this->category('ROAD', 'CRACK');
        $this->submit($this->citizen(), $this->reportPayload($road, 3005, ['issue_category_id' => $crack->id]))->assertCreated();
        $this->submit($this->citizen(), $this->reportPayload($road, 3200))->assertCreated();

        [$first, $second, $otherCategory, $farther] = Report::orderBy('id')->get()->all();
        $this->assertNotContains('possible_duplicate', $first->location_flags ?? []);
        $this->assertContains('possible_duplicate', $second->location_flags);
        $this->assertNotContains('possible_duplicate', $otherCategory->location_flags ?? []);
        $this->assertNotContains('possible_duplicate', $farther->location_flags ?? []);
        $this->assertSame(4, Report::count()); // nothing deleted or merged automatically
    }

    #[Test]
    public function department_route_applies_where_maintenance_has_expired(): void
    {
        $this->submit($this->citizen(), $this->reportPayload($this->road('RCD-015'), 1500))->assertCreated();

        $resp = Report::sole()->currentResponsibility;
        $this->assertSame('department', $resp->route);
        $this->assertNull($resp->contractor_id);
    }

    #[Test]
    public function report_numbers_restart_each_financial_year(): void
    {
        $road = $this->road('RCD-005');
        $this->submit($this->citizen(), $this->reportPayload($road, 3000))->assertJsonPath('report_no', 'RCD/2026-27/000001');
        $this->submit($this->citizen(), $this->reportPayload($road, 6000))->assertJsonPath('report_no', 'RCD/2026-27/000002');

        $this->travelTo(now()->setDate(2027, 4, 1)->setTime(9, 0));
        $this->submit($this->citizen(), $this->reportPayload($road, 9000))->assertJsonPath('report_no', 'RCD/2027-28/000001');
    }

    #[Test]
    public function the_form_and_live_preview_work(): void
    {
        $road = $this->road('RCD-005');
        [$lat, $lng] = $this->pointOn($road, 4000);

        $this->actingAs($this->citizen())->get('/reports/create')->assertOk()->assertSee('reportConfig', false);
        $this->actingAs($this->citizen())->getJson("/reports/preview.json?lat={$lat}&lng={$lng}&accuracy=6")
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('road.code', 'RCD-005')->assertJsonPath('section', 'S01');
        $this->actingAs($this->citizen())->getJson('/reports/preview.json?lat=20&lng=80&accuracy=6')
            ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('message', 'You are not currently within the permitted reporting area.');
    }
}
