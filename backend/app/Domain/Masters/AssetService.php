<?php

namespace App\Domain\Masters;

use App\Domain\Gis\GisEngine;
use App\Domain\Gis\RoadGeometryService;
use App\Models\Asset;
use App\Models\GisFeature;
use App\Models\Road;
use App\Models\RoadSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assets are located by road + chainage. The section is derived from the chainage;
 * coordinates come from the road geometry unless surveyed coordinates are given.
 */
class AssetService
{
    public function __construct(
        private readonly RoadGeometryService $geometry,
        private readonly GisEngine $gis,
    ) {}

    /** @param array<string, mixed> $data */
    public function save(Asset $asset, array $data): Asset
    {
        return DB::transaction(function () use ($asset, $data) {
            $road = Road::findOrFail($data['road_id']);
            $chainage = isset($data['chainage_m']) ? (int) $data['chainage_m'] : null;

            if ($chainage !== null && ($chainage < $road->start_chainage_m || $chainage > $road->end_chainage_m)) {
                throw ValidationException::withMessages(['chainage_m' => 'Chainage is outside the road.']);
            }
            if (isset($data['end_chainage_m']) && $chainage !== null && $data['end_chainage_m'] <= $chainage) {
                throw ValidationException::withMessages(['end_chainage_m' => 'End chainage must be after the start chainage.']);
            }

            $data['road_section_id'] = $chainage === null ? null : RoadSection::where('road_id', $road->id)
                ->where('start_chainage_m', '<=', $chainage)->where('end_chainage_m', '>=', $chainage)
                ->orderBy('start_chainage_m')->value('id');

            if (empty($data['latitude']) || empty($data['longitude'])) {
                [$data['latitude'], $data['longitude']] = $this->coordinatesFromRoad($road, $chainage);
            }

            $data['geojson'] = $data['latitude'] !== null
                ? json_encode(['type' => 'Point', 'coordinates' => [round((float) $data['longitude'], 7), round((float) $data['latitude'], 7)]])
                : null;
            $data['is_test'] = $data['is_test'] ?? $road->is_test;

            $asset->fill($data)->save();

            if ($asset->latitude !== null) {
                $this->gis->indexPoint(GisFeature::TYPE_ASSET, $asset->id, $asset->latitude, $asset->longitude);
                $this->gis->setActive(GisFeature::TYPE_ASSET, $asset->id, $asset->status === 'active');
            } else {
                $this->gis->remove(GisFeature::TYPE_ASSET, $asset->id);
            }

            return $asset;
        });
    }

    /** @return array{0: ?float, 1: ?float} */
    private function coordinatesFromRoad(Road $road, ?int $chainage): array
    {
        $line = $chainage === null ? null : $this->geometry->currentLine($road);
        if (! $line) {
            return [null, null];
        }

        [$lng, $lat] = $line->pointAt($this->geometry->chainageToMeasure($road, $chainage));

        return [round($lat, 7), round($lng, 7)];
    }
}
