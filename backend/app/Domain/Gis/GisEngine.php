<?php

namespace App\Domain\Gis;

/**
 * Spatial index abstraction. Business code depends only on this interface,
 * so the MySQL implementation can be replaced with PostGIS.
 */
interface GisEngine
{
    /** Insert or replace the indexed geometry of a feature. */
    public function indexLine(string $featureType, int $featureId, LineString $line): void;

    public function indexPoint(string $featureType, int $featureId, float $lat, float $lng): void;

    public function remove(string $featureType, int $featureId): void;

    public function setActive(string $featureType, int $featureId, bool $active): void;

    /**
     * IDs of active features whose bounding box intersects a radius around the point.
     * Cheap pre-filter; callers compute exact distances.
     *
     * @return list<int>
     */
    public function candidatesNear(string $featureType, float $lat, float $lng, float $radiusM, int $limit = 50): array;

    /**
     * IDs of active features intersecting a bounding box (map viewport).
     *
     * @return list<int>
     */
    public function featuresInBbox(string $featureType, float $minLat, float $minLng, float $maxLat, float $maxLng, int $limit = 2000): array;
}
