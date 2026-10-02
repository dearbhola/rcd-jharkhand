<?php

namespace App\Domain\Gis;

use App\Models\Road;
use App\Models\RoadChainageMarker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Km-stone calibration markers. Adding or removing one re-cuts the road's sections.
 */
class ChainageMarkerService
{
    /** A marker further than this from the drawn line is almost certainly a mistake. */
    private const MAX_OFFSET_M = 100;

    public function __construct(
        private readonly RoadGeometryService $geometry,
        private readonly RoadLineRepository $lines,
    ) {}

    public function add(Road $road, int $chainageM, float $lat, float $lng, ?string $label = null): RoadChainageMarker
    {
        return DB::transaction(function () use ($road, $chainageM, $lat, $lng, $label) {
            $road = Road::lockForUpdate()->findOrFail($road->id);
            $line = $this->geometry->currentLine($road)
                ?? throw ValidationException::withMessages(['chainage_km' => 'Draw the road geometry before adding km markers.']);

            if ($chainageM < $road->start_chainage_m || $chainageM > $road->end_chainage_m) {
                throw ValidationException::withMessages(['chainage_km' => 'The marker chainage is outside the road.']);
            }
            if ($road->chainageMarkers()->where('chainage_m', $chainageM)->exists()) {
                throw ValidationException::withMessages(['chainage_km' => 'A marker already exists at this chainage.']);
            }

            $hit = $line->locate($lat, $lng);
            if ($hit['distance_m'] > self::MAX_OFFSET_M) {
                throw ValidationException::withMessages(['latitude' => sprintf('The marker is %.0f m from the road line (max %d m).', $hit['distance_m'], self::MAX_OFFSET_M)]);
            }

            // Markers must increase along the line: compare with neighbours by chainage.
            foreach ($road->chainageMarkers()->get() as $other) {
                $otherMeasure = $line->locate($other->latitude, $other->longitude)['measure_m'];
                if (($other->chainage_m < $chainageM) !== ($otherMeasure < $hit['measure_m'])) {
                    throw ValidationException::withMessages(['chainage_km' => "Marker order conflicts with km {$other->chainage_m}: chainage must increase along the drawn direction."]);
                }
            }

            $marker = $road->chainageMarkers()->create([
                'chainage_m' => $chainageM, 'latitude' => $lat, 'longitude' => $lng, 'label' => $label,
            ]);
            $this->recut($road, $line);

            return $marker;
        });
    }

    public function remove(RoadChainageMarker $marker): void
    {
        DB::transaction(function () use ($marker) {
            $road = Road::lockForUpdate()->findOrFail($marker->road_id);
            $marker->delete(); // audited by the model
            $this->recut($road, $this->geometry->currentLine($road));
        });
    }

    private function recut(Road $road, ?LineString $line): void
    {
        $this->lines->forget($road->id);
        $this->geometry->rebuildSectionGeometries($road, $line);
    }
}
