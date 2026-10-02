<?php

namespace Tests\Feature\Phase4;

use App\Domain\Gis\GeoMath;
use App\Domain\Gis\LineString;
use App\Domain\Gis\LocationResolver;
use App\Domain\Responsibility\Resolution;
use App\Domain\Responsibility\ResponsibilityResolver;
use App\Models\Asset;
use App\Models\ContractRoadSection;
use App\Models\Road;
use App\Models\RoadSection;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

class LocationResolutionTest extends SeededTestCase
{
    /** A point on the road at a chainage, optionally pushed sideways (east) by some metres. */
    private function pointAt(Road $road, int $chainageM, float $offsetEastM = 0): array
    {
        $line = LineString::fromGeoJson($road->currentGeometry->geojson);
        [$lng, $lat] = $line->pointAt($chainageM - $road->start_chainage_m);

        return [$lat, $lng + $offsetEastM / (GeoMath::EARTH_RADIUS_M * cos(deg2rad($lat))) * 180 / M_PI];
    }

    #[Test]
    public function a_point_on_a_road_resolves_to_its_section_and_chainage(): void
    {
        $road = $this->road('RCD-005');
        [$lat, $lng] = $this->pointAt($road, 12345);

        $match = app(LocationResolver::class)->nearest($lat, $lng, 50);

        $this->assertNotNull($match);
        $this->assertSame($road->id, $match->road->id);
        $this->assertSame('S02', $match->section->code);
        $this->assertEqualsWithDelta(12345, $match->chainageM, 3);
        $this->assertLessThan(1, $match->distanceM);
    }

    #[Test]
    public function the_radius_limits_matches(): void
    {
        $road = $this->road('RCD-005');
        [$lat, $lng] = $this->pointAt($road, 5000, 35);
        $resolver = app(LocationResolver::class);

        $match = $resolver->nearest($lat, $lng, 50);
        $this->assertNotNull($match);
        $this->assertEqualsWithDelta(35, $match->distanceM, 8); // road bends, so not exactly perpendicular
        $this->assertNull($resolver->nearest($lat + 0.01, $lng + 0.01, 50)); // ~1.5 km away
    }

    #[Test]
    public function inactive_sections_are_not_matched(): void
    {
        $road = $this->road('RCD-005');
        [$lat, $lng] = $this->pointAt($road, 3000);
        RoadSection::where('road_id', $road->id)->where('code', 'S01')->update(['status' => 'inactive']);

        $match = app(LocationResolver::class)->nearest($lat, $lng, 50);
        $this->assertTrue($match === null || $match->road->id !== $road->id);
    }

    #[Test]
    public function the_nearest_asset_is_identified(): void
    {
        $asset = Asset::where('code', 'RCD-005-BRIDGE-01')->firstOrFail();

        $match = app(LocationResolver::class)->nearest($asset->latitude, $asset->longitude, 50, 30);

        $this->assertSame($asset->id, $match->asset?->id);
        $this->assertLessThan(1, $match->assetDistanceM);
    }

    #[Test]
    public function a_road_in_maintenance_routes_to_its_contractor_with_the_mapped_officers(): void
    {
        $road = $this->road('RCD-005');
        [$lat, $lng] = $this->pointAt($road, 2000);

        $resolution = app(ResponsibilityResolver::class)->resolve(app(LocationResolver::class)->nearest($lat, $lng, 50), Carbon::parse('2026-10-02'));

        $this->assertTrue($resolution->maintenanceActive);
        $this->assertSame(Resolution::ROUTE_CONTRACTOR, $resolution->route());
        $this->assertNotNull($resolution->contractor);
        $this->assertSame([], $resolution->gaps());
        $this->assertTrue($resolution->je->hasRole('JE'));
    }

    #[Test]
    public function expired_or_missing_maintenance_routes_to_the_department(): void
    {
        $resolve = function (string $code, string $date) {
            [$lat, $lng] = $this->pointAt($this->road($code), 1500);

            return app(ResponsibilityResolver::class)->resolve(app(LocationResolver::class)->nearest($lat, $lng, 50), Carbon::parse($date));
        };

        // RCD-015: maintenance (and coverage) ended 31-Mar-2026.
        $before = $resolve('RCD-015', '2026-03-01');
        $after = $resolve('RCD-015', '2026-10-02');
        $this->assertSame(Resolution::ROUTE_CONTRACTOR, $before->route());
        $this->assertSame(Resolution::ROUTE_DEPARTMENT, $after->route());
        $this->assertNull($after->contract);

        // RCD-019: never had a contract.
        $none = $resolve('RCD-019', '2026-10-02');
        $this->assertSame(Resolution::ROUTE_DEPARTMENT, $none->route());
        $this->assertNull($none->contractor);
        $this->assertNotNull($none->je); // department flow still has its officers
    }

    #[Test]
    public function a_contract_still_covering_the_road_after_maintenance_ends_routes_to_the_department(): void
    {
        $road = $this->road('RCD-015');
        ContractRoadSection::where('road_id', $road->id)->update(['effective_to' => null]);
        [$lat, $lng] = $this->pointAt($road, 1500);

        $resolution = app(ResponsibilityResolver::class)->resolve(app(LocationResolver::class)->nearest($lat, $lng, 50), Carbon::parse('2026-10-02'));

        $this->assertNotNull($resolution->contract); // still mapped…
        $this->assertFalse($resolution->maintenanceActive); // …but outside maintenance dates
        $this->assertSame(Resolution::ROUTE_DEPARTMENT, $resolution->route());
    }

    #[Test]
    public function historical_dates_resolve_historical_contractor_and_je(): void
    {
        $road = $this->road('RCD-001');
        [$lat, $lng] = $this->pointAt($road, 1000); // S01: earlier contract 2021–24, JE changed 01-Jul-2026
        $match = app(LocationResolver::class)->nearest($lat, $lng, 50);
        $resolver = app(ResponsibilityResolver::class);

        $in2022 = $resolver->resolve($match, Carbon::parse('2022-06-01'));
        $now = $resolver->resolve($match, Carbon::parse('2026-10-02'));
        $june = $resolver->resolve($match, Carbon::parse('2026-06-15'));

        $this->assertTrue($in2022->maintenanceActive);
        $this->assertNotSame($in2022->contractor->id, $now->contractor->id);
        $this->assertNotSame($june->je->id, $now->je->id);
    }
}
