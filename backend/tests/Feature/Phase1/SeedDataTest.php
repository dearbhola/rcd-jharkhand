<?php

namespace Tests\Feature\Phase1;

use App\Models\Asset;
use App\Models\Contract;
use App\Models\Division;
use App\Models\Road;
use App\Models\RoadSection;
use App\Models\SubDivision;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Support\TestDataMode;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

class SeedDataTest extends SeededTestCase
{
    #[Test]
    public function it_seeds_the_required_demo_volumes(): void
    {
        $this->assertSame(2, Division::count());
        $this->assertSame(4, SubDivision::count());
        $this->assertSame(20, Road::count());
        $this->assertGreaterThan(20, RoadSection::count());
        $this->assertGreaterThan(20, Asset::count());
        $this->assertSame(2, WorkflowDefinition::count());
    }

    #[Test]
    public function every_demo_record_is_flagged_as_test_data(): void
    {
        foreach ([Division::class, SubDivision::class, Road::class, RoadSection::class, Asset::class, Contract::class] as $model) {
            $this->assertSame(0, $model::where('is_test', false)->count(), "{$model} has non-test demo rows");
        }
        $this->assertSame(0, User::where('is_test', false)->count());
    }

    #[Test]
    public function test_data_is_hidden_when_excluded(): void
    {
        $mode = app(TestDataMode::class);
        $mode->include(false);

        $this->assertSame(0, Road::count());
        $this->assertSame(0, Contract::count());

        $mode->include(true);
        $this->assertSame(20, Road::count());
    }

    #[Test]
    public function forced_exclusion_overrides_an_explicit_include(): void
    {
        $mode = app(TestDataMode::class);
        $mode->include(true);
        $mode->forceExclude();

        $this->assertSame(0, Road::count());
    }

    #[Test]
    public function sections_tile_each_road_without_gaps_and_carry_geometry(): void
    {
        foreach (Road::with('sections')->get() as $road) {
            $expectedStart = $road->start_chainage_m;
            foreach ($road->sections as $section) {
                $this->assertSame($expectedStart, $section->start_chainage_m, "{$road->code}/{$section->code} gap");
                $this->assertNotNull($section->geojson);
                $expectedStart = $section->end_chainage_m;
            }
            $this->assertSame($road->end_chainage_m, $expectedStart, "{$road->code} not fully covered");
        }
    }
}
