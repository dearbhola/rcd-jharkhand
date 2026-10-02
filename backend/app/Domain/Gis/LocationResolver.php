<?php

namespace App\Domain\Gis;

use App\Models\Asset;
use App\Models\GisFeature;
use App\Models\Road;
use App\Models\RoadSection;
use Illuminate\Support\Collection;

/**
 * GPS point → nearest road section within a radius → chainage (+ nearest asset).
 *
 * Two stages: the GIS engine's spatial index returns candidate sections by bounding
 * box (cheap); exact point-to-line distance is then computed here.
 */
class LocationResolver
{
    public function __construct(
        private readonly GisEngine $gis,
        private readonly RoadLineRepository $lines,
    ) {}

    public function nearest(float $lat, float $lng, float $radiusM, ?float $assetRadiusM = null): ?LocationMatch
    {
        return $this->candidates($lat, $lng, $radiusM, $assetRadiusM)->first();
    }

    /**
     * All roads within the radius, nearest first (one match per road).
     *
     * @return Collection<int, LocationMatch>
     */
    public function candidates(float $lat, float $lng, float $radiusM, ?float $assetRadiusM = null): Collection
    {
        $ids = $this->gis->candidatesNear(GisFeature::TYPE_SECTION, $lat, $lng, $radiusM);
        if ($ids === []) {
            return collect();
        }

        $sections = RoadSection::query()
            ->with('road')
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->whereNotNull('geojson')
            ->whereHas('road', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->get();

        $best = [];
        foreach ($sections as $section) {
            $hit = LineString::fromGeoJson($section->geojson)->locate($lat, $lng);
            if ($hit['distance_m'] > $radiusM) {
                continue;
            }
            if (! isset($best[$section->road_id]) || $hit['distance_m'] < $best[$section->road_id][1]['distance_m']) {
                $best[$section->road_id] = [$section, $hit];
            }
        }

        $asset = $this->nearestAsset($lat, $lng, $assetRadiusM ?? $radiusM);

        return collect($best)
            ->map(function (array $pair) use ($lat, $lng, $asset) {
                [$section, $hit] = $pair;
                $road = $section->road;
                $chainage = $this->chainageOnRoad($road, $lat, $lng) ?? $section->start_chainage_m;
                // Keep chainage consistent with the matched section's range.
                $chainage = max($section->start_chainage_m, min($section->end_chainage_m, $chainage));
                $ownAsset = $asset && $asset[0]->road_id === $road->id ? $asset : null;

                return new LocationMatch($road, $section, $chainage, $hit['distance_m'], $hit['lat'], $hit['lng'], $ownAsset[0] ?? null, $ownAsset[1] ?? null);
            })
            ->sortBy('distanceM')
            ->values();
    }

    public function chainageOnRoad(Road $road, float $lat, float $lng): ?int
    {
        $line = $this->lines->line($road);

        return $line ? $this->lines->scale($road)->chainageAt($line->locate($lat, $lng)['measure_m']) : null;
    }

    /** @return array{0: Asset, 1: float}|null */
    private function nearestAsset(float $lat, float $lng, float $radiusM): ?array
    {
        $ids = $this->gis->candidatesNear(GisFeature::TYPE_ASSET, $lat, $lng, $radiusM);
        if ($ids === []) {
            return null;
        }

        return Asset::with('type:id,name')->whereIn('id', $ids)->where('status', '!=', 'decommissioned')->whereNotNull('latitude')->get()
            ->map(fn (Asset $a) => [$a, GeoMath::distance($lat, $lng, $a->latitude, $a->longitude)])
            ->filter(fn ($pair) => $pair[1] <= $radiusM)
            ->sortBy(1)
            ->first();
    }
}
