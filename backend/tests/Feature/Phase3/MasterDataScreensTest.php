<?php

namespace Tests\Feature\Phase3;

use App\Models\AssetType;
use App\Models\Contract;
use App\Models\Contractor;
use App\Models\ContractRoadSection;
use App\Models\IssueCategory;
use App\Models\Permission;
use App\Models\ResponsibilityAssignment;
use App\Models\Road;
use App\Models\Severity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

class MasterDataScreensTest extends SeededTestCase
{
    private function admin(): User
    {
        return $this->userByEmail('admin@rcd.test');
    }

    #[Test]
    public function every_master_screen_renders_for_an_admin(): void
    {
        $road = $this->road('RCD-001');
        $section = $road->sections()->first();
        $contract = Contract::first();
        $asset = $road->assets()->first();

        $urls = [
            '/divisions', '/divisions/create', '/divisions/'.$road->division_id.'/edit', '/divisions/'.$road->division_id.'/sub-divisions/create',
            '/categories', '/categories?tab=severities', '/categories?tab=roads',
            '/roads', '/roads?q=RCD&status=active', '/roads/create', "/roads/{$road->id}", "/roads/{$road->id}/edit",
            "/road-sections/{$section->id}", "/road-sections/{$section->id}/edit",
            '/assets', '/assets/create', "/assets/{$asset->id}", "/assets/{$asset->id}/edit",
            '/contractors', '/contractors/create', "/contractors/{$contract->contractor_id}", "/contractors/{$contract->contractor_id}/edit",
            '/contracts', '/contracts?maintenance=active', '/contracts/create', "/contracts/{$contract->id}", "/contracts/{$contract->id}/edit",
            '/responsibility', '/responsibility?unmapped=1', '/responsibility/assign',
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    #[Test]
    public function engineers_can_view_but_not_edit_masters_and_citizens_see_nothing(): void
    {
        $je = $this->userByEmail('je001@rcd.test');
        $citizen = User::where('mobile', '9500000001')->firstOrFail();
        $road = $this->road('RCD-001');

        $this->actingAs($je)->get('/roads')->assertOk();
        $this->actingAs($je)->get("/roads/{$road->id}")->assertOk()->assertDontSee('Add section');
        $this->actingAs($je)->get('/roads/create')->assertForbidden();
        $this->actingAs($je)->put("/roads/{$road->id}", [])->assertForbidden();
        $this->actingAs($je)->get('/responsibility/assign')->assertForbidden();
        $this->actingAs($je)->get('/categories')->assertForbidden();

        foreach (['/roads', '/assets', '/contracts', '/contractors', '/responsibility', '/divisions'] as $url) {
            $this->actingAs($citizen)->get($url)->assertForbidden();
        }
    }

    #[Test]
    public function roads_are_created_with_km_converted_to_metres(): void
    {
        $response = $this->actingAs($this->admin())->post('/roads', [
            'code' => 'rcd-101', 'name' => 'New Link Road', 'division_id' => $this->road('RCD-001')->division_id,
            'start_km' => '0', 'end_km' => '12.345', 'status' => 'active',
        ]);

        $road = Road::where('code', 'RCD-101')->firstOrFail();
        $response->assertRedirect("/roads/{$road->id}");
        $this->assertSame(12345, $road->end_chainage_m);
        $this->assertSame(12345, $road->length_m);
        $this->assertFalse($road->is_test);
        $this->assertDatabaseHas('audit_logs', ['action' => 'road.created', 'auditable_id' => $road->id]);
    }

    #[Test]
    public function road_validation_rejects_bad_chainage_and_mismatched_sub_division(): void
    {
        $roads = Road::with('division.subDivisions')->get();
        $otherDivisionSub = $roads->firstWhere('division_id', '!=', $roads->first()->division_id)->division->subDivisions->first();

        $this->actingAs($this->admin())->post('/roads', [
            'code' => 'RCD-102', 'name' => 'Bad', 'division_id' => $roads->first()->division_id, 'sub_division_id' => $otherDivisionSub->id,
            'start_km' => '5', 'end_km' => '4', 'status' => 'active',
        ])->assertSessionHasErrors(['end_chainage_m', 'sub_division_id']);
    }

    #[Test]
    public function only_test_data_users_can_flag_records_as_test(): void
    {
        // Give JE road.create temporarily to isolate the is_test rule.
        $je = $this->userByEmail('je001@rcd.test');
        $je->roles()->first()->permissions()->attach(Permission::where('key', 'road.create')->value('id'));
        $payload = ['name' => 'Flag test', 'division_id' => $this->road('RCD-001')->division_id, 'start_km' => 0, 'end_km' => 1, 'status' => 'active', 'is_test' => 1];

        $this->actingAs($je->fresh())->post('/roads', ['code' => 'RCD-201', ...$payload]);
        $this->actingAs($this->admin())->post('/roads', ['code' => 'RCD-202', ...$payload]);

        $this->assertFalse(Road::where('code', 'RCD-201')->sole()->is_test);
        $this->assertTrue(Road::where('code', 'RCD-202')->sole()->is_test);
    }

    #[Test]
    public function sections_are_added_and_split_through_the_road_screen(): void
    {
        $road = Road::create(['code' => 'RCD-301', 'name' => 'Split road', 'division_id' => $this->road('RCD-001')->division_id,
            'start_chainage_m' => 0, 'end_chainage_m' => 10000, 'length_m' => 10000, 'status' => 'active']);

        $this->actingAs($this->admin())->post("/roads/{$road->id}/sections", [
            'code' => 's01', 'start_km' => '0', 'end_km' => '10', 'division_id' => $road->division_id, 'status' => 'active',
        ])->assertRedirect("/roads/{$road->id}");
        $section = $road->sections()->sole();
        $this->assertSame('S01', $section->code);

        $this->actingAs($this->admin())->post("/road-sections/{$section->id}/split", ['split_km' => '6.5', 'new_code' => 's02'])->assertRedirect();
        $this->assertSame([6500, 10000], [$section->fresh()->end_chainage_m, $road->sections()->where('code', 'S02')->sole()->end_chainage_m]);

        $this->actingAs($this->admin())->post("/roads/{$road->id}/sections", [
            'code' => 'S03', 'start_km' => '9', 'end_km' => '10', 'division_id' => $road->division_id, 'status' => 'active',
        ])->assertSessionHasErrors('start_chainage_m');
    }

    #[Test]
    public function the_sections_lookup_returns_a_roads_sections(): void
    {
        $road = $this->road('RCD-002');

        $this->actingAs($this->userByEmail('je001@rcd.test'))->getJson("/roads/{$road->id}/sections.json")
            ->assertOk()
            ->assertJsonCount($road->sections()->count(), 'sections')
            ->assertJsonPath('sections.0.code', 'S01');
    }

    #[Test]
    public function categories_and_severities_are_configurable_and_deactivated_not_deleted(): void
    {
        $bridge = AssetType::where('code', 'BRIDGE')->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/asset-types/{$bridge->id}/categories", ['name' => 'Scour', 'is_active' => 1])->assertRedirect();
        $scour = IssueCategory::where('asset_type_id', $bridge->id)->where('code', 'SCOUR')->sole();

        $this->actingAs($admin)->put("/issue-categories/{$scour->id}", ['name' => 'Scour at pier', 'code' => 'SCOUR', 'is_active' => 0])->assertRedirect();
        $this->assertFalse($scour->fresh()->is_active);
        $this->assertSame('Scour at pier', $scour->fresh()->name);

        $low = Severity::where('code', 'LOW')->sole();
        $this->actingAs($admin)->put("/severities/{$low->id}", ['name' => 'Low', 'code' => 'LOW', 'rank' => 4, 'color' => '#00ff00', 'is_active' => 1])
            ->assertSessionHasErrors('rank'); // rank 4 already belongs to CRITICAL
    }

    #[Test]
    public function contract_maintenance_dates_are_validated(): void
    {
        $this->actingAs($this->admin())->post('/contracts', [
            'contract_no' => 'NEW/001', 'contractor_id' => Contractor::value('id'), 'status' => 'active',
            'maintenance_start_date' => '2027-01-01', 'maintenance_end_date' => '2026-01-01',
        ])->assertSessionHasErrors('maintenance_end_date');

        $this->actingAs($this->admin())->post('/contracts', [
            'contract_no' => 'NEW/002', 'contractor_id' => Contractor::value('id'), 'status' => 'active', 'maintenance_start_date' => '2027-01-01',
        ])->assertSessionHasErrors('maintenance_end_date');
    }

    #[Test]
    public function overlapping_coverage_is_refused_through_the_contract_screen(): void
    {
        $road = $this->road('RCD-005');
        $contract = Contract::where('id', '!=', ContractRoadSection::where('road_id', $road->id)->value('contract_id'))->first();

        $this->actingAs($this->admin())->post("/contracts/{$contract->id}/mappings", [
            'road_id' => $road->id, 'start_km' => '1', 'end_km' => '2', 'effective_from' => '2026-12-01',
        ])->assertSessionHasErrors('road_id');
        $this->assertSame(1, ContractRoadSection::where('road_id', $road->id)->count());
    }

    #[Test]
    public function contract_documents_are_stored_privately_and_downloadable(): void
    {
        Storage::fake('local');
        $contract = Contract::first();

        $this->actingAs($this->admin())->post("/contracts/{$contract->id}/documents", [
            'title' => 'Agreement', 'file' => UploadedFile::fake()->create('agreement.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $doc = $contract->documents()->sole();
        Storage::disk('local')->assertExists($doc->path);
        $this->assertSame(64, strlen($doc->sha256));

        $this->actingAs($this->userByEmail('je001@rcd.test'))->get("/contract-documents/{$doc->id}")->assertOk()->assertDownload('agreement.pdf');

        $this->actingAs($this->admin())->post("/contracts/{$contract->id}/documents", [
            'title' => 'Bad', 'file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('file');
    }

    #[Test]
    public function officers_are_assigned_through_the_responsibility_screen(): void
    {
        $road = $this->road('RCD-007');
        $sections = $road->sections()->pluck('id')->all();
        $newAe = $this->userByEmail('ae.sdn1@rcd.test'); // RCD-007 is currently mapped to ae.sdn2

        $this->actingAs($this->admin())->post('/responsibility', [
            'road_id' => $road->id, 'sections' => $sections, 'role_code' => 'AE', 'user_id' => $newAe->id,
            'effective_from' => now()->addDay()->toDateString(), 'remarks' => 'Office order 7',
        ])->assertRedirect("/roads/{$road->id}");

        foreach ($sections as $id) {
            $this->assertSame($newAe->id, ResponsibilityAssignment::forScope('road_section', $id)->where('role_code', 'AE')->whereNull('effective_to')->sole()->user_id);
            $this->assertSame(2, ResponsibilityAssignment::forScope('road_section', $id)->where('role_code', 'AE')->count());
        }

        // Sections from another road are refused.
        $this->actingAs($this->admin())->post('/responsibility', [
            'road_id' => $road->id, 'sections' => [$this->road('RCD-001')->sections()->value('id')], 'role_code' => 'AE',
            'user_id' => $newAe->id, 'effective_from' => now()->addDays(5)->toDateString(),
        ])->assertSessionHasErrors('sections.0');
    }
}
