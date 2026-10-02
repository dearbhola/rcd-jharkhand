<?php

namespace Tests\Support;

use App\Domain\Gis\GeoMath;
use App\Domain\Gis\LineString;
use App\Models\AssetType;
use App\Models\IssueCategory;
use App\Models\Road;
use App\Models\Severity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait ReportsHelper
{
    /** [lat, lng] on a road at a chainage, optionally offset east by metres. */
    protected function pointOn(Road $road, int $chainageM, float $offsetEastM = 0): array
    {
        [$lng, $lat] = LineString::fromGeoJson($road->currentGeometry->geojson)->pointAt($chainageM - $road->start_chainage_m);

        return [$lat, $lng + $offsetEastM / (GeoMath::EARTH_RADIUS_M * cos(deg2rad($lat))) * 180 / M_PI];
    }

    protected function photo(string $name = 'photo.jpg', int $w = 1600, int $h = 1200): UploadedFile
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    /** Minimal MP4 header so magic-byte detection sees video/mp4. */
    protected function video(): UploadedFile
    {
        $bytes = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 2048);

        return UploadedFile::fake()->createWithContent('clip.mp4', $bytes);
    }

    protected function category(string $typeCode = 'ROAD', string $code = 'POTHOLE'): IssueCategory
    {
        return IssueCategory::where('code', $code)->where('asset_type_id', AssetType::where('code', $typeCode)->value('id'))->firstOrFail();
    }

    /** @return array<string, mixed> */
    protected function reportPayload(Road $road, int $chainageM, array $overrides = []): array
    {
        [$lat, $lng] = $this->pointOn($road, $chainageM, $overrides['offset'] ?? 3);
        unset($overrides['offset']);
        $category = $this->category();

        return [
            'client_uuid' => (string) Str::uuid(),
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => 8,
            'captured_at' => now()->subMinutes(2)->toIso8601String(),
            'gps_fix_at' => now()->subMinutes(2)->toIso8601String(),
            'asset_type_id' => $category->asset_type_id,
            'issue_category_id' => $category->id,
            'severity_id' => Severity::where('code', 'HIGH')->value('id'),
            'description' => 'Deep pothole on the left lane',
            'evidence' => [$this->photo('a.jpg'), $this->photo('b.jpg', 1200, 1600)],
            ...$overrides,
        ];
    }
}
