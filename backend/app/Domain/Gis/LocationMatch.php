<?php

namespace App\Domain\Gis;

use App\Models\Asset;
use App\Models\Road;
use App\Models\RoadSection;

/**
 * Result of locating a GPS point on the road network.
 */
final class LocationMatch
{
    public function __construct(
        public readonly Road $road,
        public readonly RoadSection $section,
        public readonly int $chainageM,
        public readonly float $distanceM,
        public readonly float $snappedLat,
        public readonly float $snappedLng,
        public readonly ?Asset $asset = null,
        public readonly ?float $assetDistanceM = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'road' => ['id' => $this->road->id, 'code' => $this->road->code, 'name' => $this->road->name],
            'section' => ['id' => $this->section->id, 'code' => $this->section->code],
            'chainage_m' => $this->chainageM,
            'chainage_km' => km($this->chainageM),
            'distance_m' => round($this->distanceM, 1),
            'snapped' => ['lat' => round($this->snappedLat, 7), 'lng' => round($this->snappedLng, 7)],
            'asset' => $this->asset ? [
                'id' => $this->asset->id, 'code' => $this->asset->code, 'name' => $this->asset->name,
                'type' => $this->asset->type?->name, 'distance_m' => round((float) $this->assetDistanceM, 1),
            ] : null,
        ];
    }
}
