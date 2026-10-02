<?php

namespace Tests\Feature\Phase3;

use App\Domain\Gis\GisEngine;
use App\Domain\Gis\LineString;
use App\Domain\Gis\RoadGeometryService;
use App\Domain\Masters\AssetService;
use App\Domain\Masters\ContractMappingService;
use App\Domain\Masters\ResponsibilityService;
use App\Domain\Masters\RoadSectionService;
use App\Enums\RoleCode;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Contract;
use App\Models\ContractRoadSection;
use App\Models\GisFeature;
use App\Models\ResponsibilityAssignment;
use App\Models\Road;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

class MasterDataRulesTest extends SeededTestCase
{
    private function freshRoad(int $lengthM = 20000): Road
    {
        $road = Road::create([
            'code' => 'T-'.uniqid(), 'name' => 'Test road', 'division_id' => $this->road('RCD-001')->division_id,
            'start_chainage_m' => 0, 'end_chainage_m' => $lengthM, 'length_m' => $lengthM, 'status' => 'active',
        ]);
        // Straight north-running line, ~1 km per 0.009°.
        app(RoadGeometryService::class)->saveRoadGeometry($road, new LineString([[85.0, 23.0], [85.0, 23.0 + $lengthM / 111195]]));

        return $road->fresh();
    }

    private function sectionData(string $code, int $from, int $to, Road $road): array
    {
        return ['code' => $code, 'division_id' => $road->division_id, 'start_chainage_m' => $from, 'end_chainage_m' => $to, 'status' => 'active'];
    }

    #[Test]
    public function sections_must_lie_inside_the_road_and_not_overlap(): void
    {
        $road = $this->freshRoad();
        $service = app(RoadSectionService::class);
        $s1 = $service->create($road, $this->sectionData('S01', 0, 10000, $road));

        $this->assertNotNull($s1->fresh()->geojson);
        $this->assertContains($s1->id, app(GisEngine::class)->candidatesNear(GisFeature::TYPE_SECTION, 23.03, 85.0, 50));

        foreach ([['S02', 9000, 15000], ['S03', 15000, 25000], ['S04', 5000, 5000]] as [$code, $from, $to]) {
            try {
                $service->create($road, $this->sectionData($code, $from, $to, $road));
                $this->fail("{$code} should have been rejected");
            } catch (ValidationException) {
                $this->assertDatabaseMissing('road_sections', ['road_id' => $road->id, 'code' => $code]);
            }
        }

        $service->create($road, $this->sectionData('S02', 10000, 20000, $road));
        $this->assertSame(2, $road->sections()->count());
    }

    #[Test]
    public function splitting_a_section_keeps_history_and_copies_responsibility(): void
    {
        $road = $this->road('RCD-003');
        $section = $road->sections()->first();
        $je = ResponsibilityAssignment::forScope('road_section', $section->id)->where('role_code', 'JE')->effectiveOn(now())->sole();
        $originalEnd = $section->end_chainage_m;

        $new = app(RoadSectionService::class)->split($section, $section->start_chainage_m + 4000, 'S1B');

        $section->refresh();
        $this->assertSame($section->start_chainage_m + 4000, $section->end_chainage_m);
        $this->assertSame($section->end_chainage_m, $new->start_chainage_m);
        $this->assertSame($originalEnd, $new->end_chainage_m);
        $this->assertNotNull($new->fresh()->geojson);

        $people = app(ResponsibilityService::class)->forSection($new);
        $this->assertSame($je->user_id, $people['JE']->id);
        $this->assertNotNull($people['AE']);
        $this->assertNotNull($people['EE']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'road_section.split', 'auditable_id' => $section->id]);
    }

    #[Test]
    public function a_split_point_must_be_strictly_inside_the_section(): void
    {
        $section = $this->road('RCD-003')->sections()->first();

        $this->expectException(ValidationException::class);
        app(RoadSectionService::class)->split($section, $section->end_chainage_m, 'BAD');
    }

    #[Test]
    public function two_contracts_cannot_cover_the_same_chainage_at_the_same_time(): void
    {
        $road = $this->road('RCD-004'); // whole road covered by an open-ended mapping
        $contract = Contract::where('id', '!=', ContractRoadSection::where('road_id', $road->id)->value('contract_id'))->first();
        $service = app(ContractMappingService::class);

        try {
            $service->add($contract, ['road_id' => $road->id, 'start_chainage_m' => 1000, 'end_chainage_m' => 2000, 'effective_from' => '2026-11-01']);
            $this->fail('Overlapping coverage should be rejected');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Overlaps contract', $e->getMessage());
        }

        // End the existing coverage, then the new contract can take over from the next day.
        $existing = ContractRoadSection::where('road_id', $road->id)->sole();
        $service->end($existing, '2026-10-31', 'Contract re-tendered');
        $service->add($contract, ['road_id' => $road->id, 'effective_from' => '2026-11-01']);

        $this->assertCount(1, ContractRoadSection::covering($road->id, 1500, Carbon::parse('2026-10-15'))->get());
        $this->assertSame($contract->id, ContractRoadSection::covering($road->id, 1500, Carbon::parse('2026-11-15'))->sole()->contract_id);
        $this->assertSame(2, ContractRoadSection::where('road_id', $road->id)->count()); // history kept
    }

    #[Test]
    public function adjacent_chainage_ranges_may_have_different_contractors(): void
    {
        $road = $this->freshRoad();
        [$a, $b] = Contract::limit(2)->get();
        $service = app(ContractMappingService::class);

        $service->add($a, ['road_id' => $road->id, 'start_chainage_m' => 0, 'end_chainage_m' => 8000, 'effective_from' => '2026-01-01']);
        $service->add($b, ['road_id' => $road->id, 'start_chainage_m' => 8000, 'end_chainage_m' => 20000, 'effective_from' => '2026-01-01']);

        $this->assertSame($b->id, ContractRoadSection::covering($road->id, 12000, now())->sole()->contract_id);
    }

    #[Test]
    public function a_mapping_can_only_be_shortened_never_extended(): void
    {
        $mapping = ContractRoadSection::whereNotNull('effective_to')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(ContractMappingService::class)->end($mapping, $mapping->effective_to->copy()->addYear()->toDateString(), 'extend please');
    }

    #[Test]
    public function reassigning_a_je_closes_the_old_row_and_keeps_history(): void
    {
        $section = $this->road('RCD-006')->sections()->first();
        $newJe = $this->userByEmail('je009@rcd.test');
        $admin = $this->userByEmail('admin@rcd.test');
        $old = ResponsibilityAssignment::forScope('road_section', $section->id)->where('role_code', 'JE')->whereNull('effective_to')->sole();

        $changed = app(ResponsibilityService::class)->assign($admin, 'road_section', [$section->id], RoleCode::JE, $newJe, '2026-11-01', 'Transfer order 12/2026');

        $this->assertSame(1, $changed);
        $this->assertSame('2026-10-31', $old->fresh()->effective_to->toDateString());
        $service = app(ResponsibilityService::class);
        $this->assertSame($old->user_id, $service->forSection($section, Carbon::parse('2026-10-31'))['JE']->id);
        $this->assertSame($newJe->id, $service->forSection($section, Carbon::parse('2026-11-01'))['JE']->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'responsibility.assigned', 'auditable_id' => $section->id]);
    }

    #[Test]
    public function back_dated_assignments_and_wrong_roles_are_rejected(): void
    {
        $section = $this->road('RCD-006')->sections()->first();
        $admin = $this->userByEmail('admin@rcd.test');
        $service = app(ResponsibilityService::class);

        try {
            $service->assign($admin, 'road_section', [$section->id], RoleCode::JE, $this->userByEmail('je009@rcd.test'), '2024-01-01');
            $this->fail('Back-dated assignment should be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('effective_from', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $service->assign($admin, 'road_section', [$section->id], RoleCode::EE, $this->userByEmail('je009@rcd.test'), '2026-12-01');
    }

    #[Test]
    public function assets_take_their_section_and_position_from_the_road(): void
    {
        $road = $this->freshRoad();
        app(RoadSectionService::class)->create($road, $this->sectionData('S01', 0, 10000, $road));
        $s2 = app(RoadSectionService::class)->create($road, $this->sectionData('S02', 10000, 20000, $road));

        $asset = app(AssetService::class)->save(new Asset, [
            'code' => 'T-BR-1', 'asset_type_id' => AssetType::where('code', 'BRIDGE')->value('id'), 'name' => 'Test bridge',
            'road_id' => $road->id, 'chainage_m' => 12500, 'status' => 'active',
        ]);

        $this->assertSame($s2->id, $asset->road_section_id);
        $this->assertEqualsWithDelta(23.0 + 12500 / 111195, $asset->latitude, 0.0002);
        $this->assertEqualsWithDelta(85.0, $asset->longitude, 0.00001);
        $this->assertContains($asset->id, app(GisEngine::class)->candidatesNear(GisFeature::TYPE_ASSET, $asset->latitude, $asset->longitude, 20));
    }

    #[Test]
    public function an_asset_level_mapping_overrides_the_section_for_that_role_only(): void
    {
        $asset = Asset::whereNotNull('road_section_id')->firstOrFail();
        $sectionPeople = app(ResponsibilityService::class)->forSection($asset->section);
        $specialAe = $this->userByEmail('ae.sds2@rcd.test');

        ResponsibilityAssignment::create(['scope_type' => 'asset', 'scope_id' => $asset->id, 'role_code' => RoleCode::AE, 'user_id' => $specialAe->id, 'effective_from' => '2026-01-01']);

        $people = app(ResponsibilityService::class)->forAsset($asset);
        $this->assertSame($specialAe->id, $people['AE']->id);
        $this->assertSame($sectionPeople['JE']->id, $people['JE']->id);
    }
}
