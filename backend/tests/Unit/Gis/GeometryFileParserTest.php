<?php

namespace Tests\Unit\Gis;

use App\Domain\Gis\GeometryFileParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GeometryFileParserTest extends TestCase
{
    #[Test]
    public function it_reads_geojson_including_an_rcd_export(): void
    {
        $export = json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['feature' => 'road'], 'geometry' => ['type' => 'LineString', 'coordinates' => [[85, 23], [85, 23.1]]]],
            ['type' => 'Feature', 'properties' => ['feature' => 'road_section'], 'geometry' => ['type' => 'LineString', 'coordinates' => [[85, 23], [85, 23.05]]]],
        ]]);

        $this->assertEqualsWithDelta(11119, (new GeometryFileParser)->parse($export, 'geojson')->length(), 15);
    }

    #[Test]
    public function it_reads_kml_line_strings(): void
    {
        $kml = '<?xml version="1.0"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document><Placemark><LineString>
            <coordinates>85.0,23.0,0 85.0,23.05,0
            85.0,23.1,0</coordinates></LineString></Placemark></Document></kml>';

        $line = (new GeometryFileParser)->parse($kml, 'kml');
        $this->assertCount(3, $line->coordinates());
        $this->assertEqualsWithDelta(11119, $line->length(), 15);
    }

    #[Test]
    public function it_reads_gpx_tracks_recorded_on_site(): void
    {
        $gpx = '<?xml version="1.0"?><gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><trk><trkseg>
            <trkpt lat="23.0" lon="85.0"/><trkpt lat="23.05" lon="85.0"/><trkpt lat="23.1" lon="85.0"/></trkseg></trk></gpx>';

        $this->assertCount(3, (new GeometryFileParser)->parse($gpx, 'gpx')->coordinates());
    }

    #[Test]
    public function it_does_not_resolve_external_entities(): void
    {
        $xxe = '<?xml version="1.0"?><!DOCTYPE kml [<!ENTITY x SYSTEM "file:///etc/passwd">]>
            <kml xmlns="http://www.opengis.net/kml/2.2"><Placemark><LineString><coordinates>&x;</coordinates></LineString></Placemark></kml>';

        $this->expectException(InvalidArgumentException::class);
        (new GeometryFileParser)->parse($xxe, 'kml');
    }

    #[Test]
    public function it_rejects_unsupported_or_empty_files(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GeometryFileParser)->parse('<gpx></gpx>', 'gpx');
    }
}
