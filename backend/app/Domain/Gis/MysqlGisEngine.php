<?php

namespace App\Domain\Gis;

use App\Models\GisFeature;
use Illuminate\Support\Facades\DB;

/**
 * MySQL 8 implementation over `gis_features` (SRID 4326, spatial index).
 * WKT is always passed in lng-lat order with `axis-order=long-lat`, because
 * MySQL's default axis order for EPSG:4326 is lat-long.
 */
class MysqlGisEngine implements GisEngine
{
    private const SRID = 4326;

    private const AXIS = 'axis-order=long-lat';

    public function indexLine(string $featureType, int $featureId, LineString $line): void
    {
        $this->upsert($featureType, $featureId, $line->toWkt(), $line->bbox());
    }

    public function indexPoint(string $featureType, int $featureId, float $lat, float $lng): void
    {
        $this->upsert(
            $featureType,
            $featureId,
            sprintf('POINT(%.7F %.7F)', $lng, $lat),
            ['min_lat' => $lat, 'max_lat' => $lat, 'min_lng' => $lng, 'max_lng' => $lng],
        );
    }

    public function remove(string $featureType, int $featureId): void
    {
        GisFeature::where('feature_type', $featureType)->where('feature_id', $featureId)->delete();
    }

    public function setActive(string $featureType, int $featureId, bool $active): void
    {
        GisFeature::where('feature_type', $featureType)->where('feature_id', $featureId)->update(['is_active' => $active]);
    }

    public function candidatesNear(string $featureType, float $lat, float $lng, float $radiusM, int $limit = 50): array
    {
        $b = GeoMath::bboxAround($lat, $lng, $radiusM);

        return $this->featuresInBbox($featureType, $b['min_lat'], $b['min_lng'], $b['max_lat'], $b['max_lng'], $limit);
    }

    public function featuresInBbox(string $featureType, float $minLat, float $minLng, float $maxLat, float $maxLng, int $limit = 2000): array
    {
        $polygon = sprintf(
            'POLYGON((%1$.7F %2$.7F,%3$.7F %2$.7F,%3$.7F %4$.7F,%1$.7F %4$.7F,%1$.7F %2$.7F))',
            $minLng, $minLat, $maxLng, $maxLat,
        );

        return GisFeature::query()
            ->where('feature_type', $featureType)
            ->where('is_active', true)
            ->whereRaw('MBRIntersects(geom, ST_GeomFromText(?, ?, ?))', [$polygon, self::SRID, self::AXIS])
            ->limit($limit)
            ->pluck('feature_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param array{min_lat: float, max_lat: float, min_lng: float, max_lng: float} $bbox */
    private function upsert(string $featureType, int $featureId, string $wkt, array $bbox): void
    {
        $now = now();

        DB::statement(
            'INSERT INTO gis_features (feature_type, feature_id, geom, min_lat, max_lat, min_lng, max_lng, is_active, created_at, updated_at)
             VALUES (?, ?, ST_GeomFromText(?, ?, ?), ?, ?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE geom = VALUES(geom), min_lat = VALUES(min_lat), max_lat = VALUES(max_lat),
                 min_lng = VALUES(min_lng), max_lng = VALUES(max_lng), updated_at = VALUES(updated_at)',
            [$featureType, $featureId, $wkt, self::SRID, self::AXIS,
                $bbox['min_lat'], $bbox['max_lat'], $bbox['min_lng'], $bbox['max_lng'], $now, $now],
        );
    }
}
