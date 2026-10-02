<?php

namespace Tests\Unit\Gis;

use App\Domain\Gis\GeoMath;
use App\Domain\Gis\LineString;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LineStringTest extends TestCase
{
    /** ~1.11 km per 0.01° latitude: a straight north-running line of 0.1° ≈ 11.1 km. */
    private function northLine(): LineString
    {
        return new LineString([[85.0, 23.0], [85.0, 23.05], [85.0, 23.1]]);
    }

    #[Test]
    public function it_measures_length_geodesically(): void
    {
        $expected = GeoMath::distance(23.0, 85.0, 23.1, 85.0);

        $this->assertEqualsWithDelta($expected, $this->northLine()->length(), 0.5);
        $this->assertEqualsWithDelta(11119, $expected, 15);
    }

    #[Test]
    public function it_locates_a_point_beside_the_line(): void
    {
        $line = $this->northLine();
        // 0.0003° of longitude at 23.05°N ≈ 30.7 m east of the line, half-way along.
        $hit = $line->locate(23.05, 85.0003);

        $this->assertEqualsWithDelta(30.7, $hit['distance_m'], 1.0);
        $this->assertEqualsWithDelta($line->length() / 2, $hit['measure_m'], 1.0);
    }

    #[Test]
    public function it_clamps_points_beyond_the_ends(): void
    {
        $hit = $this->northLine()->locate(22.99, 85.0);

        $this->assertSame(0.0, $hit['measure_m']);
        $this->assertEqualsWithDelta(1112, $hit['distance_m'], 5);
    }

    #[Test]
    public function slice_and_point_at_are_consistent(): void
    {
        $line = $this->northLine();
        $slice = $line->slice(2000, 7000);

        $this->assertEqualsWithDelta(5000, $slice->length(), 1.0);
        $this->assertEqualsWithDelta($line->pointAt(2000)[1], $slice->coordinates()[0][1], 1e-7);
        $this->assertEqualsWithDelta($line->pointAt(7000)[1], $slice->coordinates()[count($slice->coordinates()) - 1][1], 1e-7);
    }

    #[Test]
    public function simplify_drops_collinear_vertices_but_keeps_ends(): void
    {
        $simplified = $this->northLine()->simplify(1.0);

        $this->assertCount(2, $simplified->coordinates());
        $this->assertEqualsWithDelta($this->northLine()->length(), $simplified->length(), 0.5);
    }

    #[Test]
    public function it_reads_geojson_variants(): void
    {
        $feature = ['type' => 'Feature', 'geometry' => ['type' => 'LineString', 'coordinates' => [[85, 23], [85, 23.1]]]];
        $multi = ['type' => 'MultiLineString', 'coordinates' => [[[85, 23], [85, 23.05]], [[85, 23.05], [85, 23.1]]]];

        $this->assertEqualsWithDelta(11119, LineString::fromGeoJson($feature)->length(), 15);
        $this->assertEqualsWithDelta(11119, LineString::fromGeoJson(json_encode($multi))->length(), 15);
    }

    #[Test]
    public function it_rejects_invalid_geometry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LineString([[85, 23], [85, 23]]); // a single distinct point
    }

    #[Test]
    public function it_rejects_out_of_range_coordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LineString([[85, 23], [85, 95]]);
    }

    #[Test]
    public function wkt_is_lng_lat_ordered(): void
    {
        $this->assertSame('LINESTRING(85.0000000 23.0000000,85.0000000 23.1000000)', (new LineString([[85, 23], [85, 23.1]]))->toWkt());
    }
}
