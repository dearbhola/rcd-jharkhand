<?php

namespace Tests\Feature\Phase4;

use App\Domain\Gis\LineString;
use App\Domain\Gis\LocationResolver;
use App\Models\GisFeature;
use App\Models\Road;
use App\Models\RoadGeometry;
use App\Models\RoadSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

class GisScreensTest extends SeededTestCase
{
    private function admin(): User
    {
        return $this->userByEmail('admin@rcd.test');
    }

    private function bboxOf(Road $road): string
    {
        $b = LineString::fromGeoJson($road->currentGeometry->geojson)->bbox();

        return implode(',', [$b['min_lng'], $b['min_lat'], $b['max_lng'], $b['max_lat']]);
    }

    private const WIDE_BBOX = '84,22,88,25';

    #[Test]
    public function map_pages_render_for_viewers_and_the_editor_needs_gis_permission(): void
    {
        $je = $this->userByEmail('je001@rcd.test');
        $road = $this->road('RCD-001');

        $this->actingAs($je)->get('/map')->assertOk()->assertSee('rcdMap', false);
        $this->actingAs($je)->get('/gis/locate')->assertOk();
        $this->actingAs($je)->get("/roads/{$road->id}/geometry")->assertForbidden();
        $this->actingAs($this->admin())->get("/roads/{$road->id}/geometry")->assertOk()->assertSee('editorData', false);
        $this->actingAs(User::where('mobile', '9500000001')->first())->get('/map')->assertForbidden();
    }

    #[Test]
    public function section_layer_is_limited_to_the_viewport_and_filterable(): void
    {
        $je = $this->userByEmail('je001@rcd.test');
        $road = $this->road('RCD-003');

        $inView = $this->actingAs($je)->getJson('/map/sections.json?zoom=12&bbox='.$this->bboxOf($road))->assertOk()->json('features');
        $this->assertContains('RCD-003', array_column(array_column($inView, 'properties'), 'road'));
        $this->assertLessThan(RoadSection::count(), count($inView));

        $all = $this->actingAs($je)->getJson('/map/sections.json?zoom=8&bbox='.self::WIDE_BBOX)->json('features');
        $this->assertCount(RoadSection::count(), $all);

        $department = $this->actingAs($je)->getJson('/map/sections.json?zoom=8&maintenance=department&bbox='.self::WIDE_BBOX)->json('features');
        $roads = array_unique(array_column(array_column($department, 'properties'), 'road'));
        sort($roads);
        $this->assertSame(['RCD-015', 'RCD-016', 'RCD-017', 'RCD-018', 'RCD-019', 'RCD-020'], $roads);

        $jeOnly = $this->actingAs($je)->getJson("/map/sections.json?zoom=8&je_id={$je->id}&bbox=".self::WIDE_BBOX)->json('features');
        $this->assertNotEmpty($jeOnly);
        $this->assertLessThan(count($all), count($jeOnly));
    }

    #[Test]
    public function geometry_is_simplified_at_low_zoom(): void
    {
        $je = $this->userByEmail('je001@rcd.test');
        $coords = fn ($zoom) => array_sum(array_map(fn ($f) => count($f['geometry']['coordinates']),
            $this->actingAs($je)->getJson("/map/sections.json?zoom={$zoom}&bbox=".self::WIDE_BBOX)->json('features')));

        $this->assertLessThan($coords(17) / 2, $coords(8));
    }

    #[Test]
    public function invalid_bbox_is_rejected(): void
    {
        $this->actingAs($this->userByEmail('je001@rcd.test'))->getJson('/map/sections.json?zoom=10&bbox=1,2,3')->assertUnprocessable();
    }

    #[Test]
    public function clicking_a_section_returns_the_full_responsibility_chain(): void
    {
        $section = $this->road('RCD-005')->sections()->first();

        $this->actingAs($this->userByEmail('je001@rcd.test'))->getJson("/map/sections/{$section->id}.json")
            ->assertOk()
            ->assertJsonPath('road.code', 'RCD-005')
            ->assertJsonPath('section.code', $section->code)
            ->assertJsonPath('contract.maintenance_active', true)
            ->assertJsonStructure(['contractor' => ['name'], 'je' => ['name'], 'ae' => ['name'], 'ee' => ['name']]);
    }

    #[Test]
    public function assets_layer_returns_points_in_view(): void
    {
        $features = $this->actingAs($this->userByEmail('je001@rcd.test'))->getJson('/map/assets.json?bbox='.self::WIDE_BBOX)->json('features');

        $this->assertCount(80, $features);
        $this->assertSame('Point', $features[0]['geometry']['type']);
    }

    #[Test]
    public function the_locate_endpoint_explains_found_and_not_found(): void
    {
        $road = $this->road('RCD-005');
        [$lng, $lat] = LineString::fromGeoJson($road->currentGeometry->geojson)->pointAt(2500);
        $je = $this->userByEmail('je001@rcd.test');

        $this->actingAs($je)->getJson("/gis/resolve.json?lat={$lat}&lng={$lng}")
            ->assertOk()->assertJsonPath('found', true)->assertJsonPath('match.road.code', 'RCD-005')->assertJsonPath('match.route', 'contractor');

        $this->actingAs($je)->getJson('/gis/resolve.json?lat=20.0&lng=80.0')
            ->assertOk()->assertJsonPath('found', false)->assertJsonPath('message', 'You are not currently within the permitted reporting area.');
    }

    #[Test]
    public function saving_geometry_creates_a_new_version_and_recuts_sections(): void
    {
        $road = $this->road('RCD-010');
        $before = $road->currentGeometry;
        $line = LineString::fromGeoJson($before->geojson);
        $shifted = array_map(fn ($c) => [$c[0] + 0.001, $c[1]], $line->coordinates()); // ~100 m east

        $this->actingAs($this->admin())->putJson("/roads/{$road->id}/geometry", [
            'geometry' => ['type' => 'LineString', 'coordinates' => $shifted], 'remarks' => 'Realigned after survey',
        ])->assertOk()->assertJsonPath('version', 2);

        $this->assertFalse($before->fresh()->is_current);
        $this->assertSame(2, RoadGeometry::where('road_id', $road->id)->count());
        $section = $road->sections()->first();
        $this->assertEqualsWithDelta($shifted[0][0], json_decode($section->geojson, true)['coordinates'][0][0], 1e-6);
        $this->assertDatabaseHas('audit_logs', ['action' => 'road.geometry_saved', 'auditable_id' => $road->id, 'comment' => 'Realigned after survey']);
        $this->assertSame(1, GisFeature::where('feature_type', 'road')->where('feature_id', $road->id)->count());
    }

    #[Test]
    public function invalid_geometry_is_rejected(): void
    {
        $road = $this->road('RCD-010');

        $this->actingAs($this->admin())->putJson("/roads/{$road->id}/geometry", ['geometry' => ['type' => 'Polygon', 'coordinates' => [[85, 23]]]])->assertUnprocessable();
        $this->actingAs($this->admin())->putJson("/roads/{$road->id}/geometry", ['geometry' => ['type' => 'LineString', 'coordinates' => [[85, 23], [85, 95]]]])
            ->assertUnprocessable()->assertJsonValidationErrors('geometry');
    }

    #[Test]
    public function files_are_imported_as_a_draft_without_saving(): void
    {
        $road = $this->road('RCD-011');
        $gpx = '<?xml version="1.0"?><gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><trk><trkseg>
            <trkpt lat="23.40" lon="85.20"/><trkpt lat="23.41" lon="85.21"/><trkpt lat="23.42" lon="85.22"/></trkseg></trk></gpx>';

        $this->actingAs($this->admin())->post("/roads/{$road->id}/geometry/import", ['file' => UploadedFile::fake()->createWithContent('drive.gpx', $gpx)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('vertices', 3)->assertJsonPath('geometry.type', 'LineString');
        $this->assertSame(1, RoadGeometry::where('road_id', $road->id)->count());

        $this->actingAs($this->admin())->post("/roads/{$road->id}/geometry/import", ['file' => UploadedFile::fake()->createWithContent('x.txt', 'hello')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    #[Test]
    public function geojson_export_round_trips(): void
    {
        $road = $this->road('RCD-001');
        $response = $this->actingAs($this->admin())->get("/roads/{$road->id}/geometry.geojson")->assertOk();

        $data = json_decode($response->streamedContent(), true);
        $this->assertSame('FeatureCollection', $data['type']);
        $this->assertSame('road', $data['features'][0]['properties']['feature']);
        $this->assertCount(1 + $road->sections()->count(), $data['features']);

        $all = json_decode($this->actingAs($this->admin())->get('/gis/export.geojson')->streamedContent(), true);
        $this->assertCount(20, array_filter($all['features'], fn ($f) => $f['properties']['feature'] === 'road'));
    }

    #[Test]
    public function km_markers_calibrate_chainage_and_recut_sections(): void
    {
        $road = $this->road('RCD-012');
        $line = LineString::fromGeoJson($road->currentGeometry->geojson);
        [$lng, $lat] = $line->pointAt(5000); // the stone for km 5.300 is found 5.000 km along the drawn line
        $s1 = $road->sections()->where('code', 'S01')->first();

        $this->actingAs($this->admin())->postJson("/roads/{$road->id}/chainage-markers", ['chainage_km' => 5.3, 'latitude' => $lat, 'longitude' => $lng])->assertCreated();

        $match = app(LocationResolver::class)->nearest($lat, $lng, 20);
        $this->assertEqualsWithDelta(5300, $match->chainageM, 3);
        // S01 (km 0–10) now ends earlier along the drawn line than before.
        $this->assertLessThan($line->slice(0, 10000)->length() - 100, LineString::fromGeoJson($s1->fresh()->geojson)->length());

        $this->actingAs($this->admin())->postJson("/roads/{$road->id}/chainage-markers", ['chainage_km' => 7, 'latitude' => $lat + 0.01, 'longitude' => $lng])
            ->assertUnprocessable()->assertJsonValidationErrors('latitude'); // ~1 km off the line
    }
}
