<?php

namespace Tests\Feature\Phase1;

use App\Domain\Gis\GisEngine;
use App\Domain\Gis\LineString;
use App\Enums\RoleCode;
use App\Models\Contract;
use App\Models\ContractRoadSection;
use App\Models\GisFeature;
use App\Models\ResponsibilityAssignment;
use App\Models\RoadSection;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

class DomainRulesTest extends SeededTestCase
{
    #[Test]
    public function maintenance_responsibility_uses_maintenance_dates_not_contract_end_date(): void
    {
        $contract = Contract::where('status', 'active')->firstOrFail();
        // Construction ended before maintenance started; the contract is still responsible during maintenance.
        $this->assertTrue($contract->end_date->lt($contract->maintenance_start_date));

        $this->assertTrue($contract->isMaintenanceActiveOn($contract->maintenance_start_date));
        $this->assertTrue($contract->isMaintenanceActiveOn($contract->maintenance_end_date));
        $this->assertFalse($contract->isMaintenanceActiveOn($contract->maintenance_start_date->copy()->subDay()));
        $this->assertFalse($contract->isMaintenanceActiveOn($contract->maintenance_end_date->copy()->addDay()));
    }

    #[Test]
    public function expired_maintenance_contracts_are_not_active(): void
    {
        $road = $this->road('RCD-015');
        $mapping = ContractRoadSection::where('road_id', $road->id)->with('contract')->sole();

        $this->assertFalse($mapping->contract->isMaintenanceActiveOn(Carbon::parse('2026-10-02')));
        $this->assertTrue($mapping->contract->isMaintenanceActiveOn(Carbon::parse('2025-10-02')));
    }

    #[Test]
    public function contract_history_is_preserved_and_resolvable_by_date(): void
    {
        $road = $this->road('RCD-001');

        $in2022 = ContractRoadSection::covering($road->id, 1000, Carbon::parse('2022-06-01'))->with('contract')->get();
        $in2026 = ContractRoadSection::covering($road->id, 1000, Carbon::parse('2026-10-02'))->with('contract')->get();

        $this->assertCount(1, $in2022);
        $this->assertCount(1, $in2026);
        $this->assertNotSame($in2022->first()->contract->contractor_id, $in2026->first()->contract->contractor_id);
    }

    #[Test]
    public function split_coverage_gives_one_contractor_per_section(): void
    {
        $road = $this->road('RCD-002');
        $sections = $road->sections()->get();
        $today = Carbon::parse('2026-10-02');

        $first = ContractRoadSection::covering($road->id, $sections->first()->start_chainage_m + 10, $today)->with('contract')->sole();
        $last = ContractRoadSection::covering($road->id, $sections->last()->start_chainage_m + 10, $today)->with('contract')->sole();

        $this->assertNotSame($first->contract->contractor_id, $last->contract->contractor_id);
    }

    #[Test]
    public function responsibility_history_returns_the_je_in_force_on_each_date(): void
    {
        $section = RoadSection::where('road_id', $this->road('RCD-001')->id)->where('code', 'S01')->firstOrFail();
        $je = fn (string $date) => ResponsibilityAssignment::forScope(ResponsibilityAssignment::SCOPE_SECTION, $section->id)
            ->where('role_code', RoleCode::JE)
            ->effectiveOn(Carbon::parse($date))
            ->sole()
            ->user_id;

        $this->assertNotSame($je('2026-06-15'), $je('2026-07-15'));
        $this->assertSame(2, ResponsibilityAssignment::forScope(ResponsibilityAssignment::SCOPE_SECTION, $section->id)->where('role_code', RoleCode::JE)->count());
    }

    #[Test]
    public function spatial_index_finds_the_section_under_a_point_and_nothing_far_away(): void
    {
        $section = RoadSection::where('road_id', $this->road('RCD-005')->id)->where('code', 'S01')->firstOrFail();
        $line = LineString::fromGeoJson($section->geojson);
        [$lng, $lat] = $line->pointAt(3000);
        $gis = app(GisEngine::class);

        $this->assertContains($section->id, $gis->candidatesNear(GisFeature::TYPE_SECTION, $lat, $lng, 50));
        // 0.5° away (~50 km) is outside every demo road's bounding box neighbourhood.
        $this->assertSame([], $gis->candidatesNear(GisFeature::TYPE_SECTION, $lat + 0.5, $lng + 0.5, 50));
    }
}
