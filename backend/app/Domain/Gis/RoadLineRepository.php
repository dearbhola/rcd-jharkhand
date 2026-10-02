<?php

namespace App\Domain\Gis;

use App\Models\Road;

/**
 * Request-scoped cache of parsed road lines and chainage scales (GeoJSON parsing
 * and marker projection are the expensive parts of a location lookup).
 */
class RoadLineRepository
{
    /** @var array<int, array{line: ?LineString, scale: ?ChainageScale}> */
    private array $cache = [];

    public function line(Road $road): ?LineString
    {
        return $this->load($road)['line'];
    }

    public function scale(Road $road): ?ChainageScale
    {
        return $this->load($road)['scale'];
    }

    public function forget(int $roadId): void
    {
        unset($this->cache[$roadId]);
    }

    /** @return array{line: ?LineString, scale: ?ChainageScale} */
    private function load(Road $road): array
    {
        return $this->cache[$road->id] ??= (function () use ($road) {
            $geojson = $road->currentGeometry()->value('geojson');
            if (! $geojson) {
                return ['line' => null, 'scale' => null];
            }

            $line = LineString::fromGeoJson($geojson);
            $markers = $road->chainageMarkers()->get(['chainage_m', 'latitude', 'longitude'])->toArray();

            return ['line' => $line, 'scale' => ChainageScale::build($line, $road->start_chainage_m, $markers)];
        })();
    }
}
