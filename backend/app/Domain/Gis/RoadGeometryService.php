<?php

namespace App\Domain\Gis;

use App\Domain\Audit\AuditLogger;
use App\Models\GisFeature;
use App\Models\Road;
use App\Models\RoadGeometry;
use App\Models\RoadSection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Writes road geometry (as a new version), derives section geometry from it by
 * chainage, and keeps the spatial index in sync.
 */
class RoadGeometryService
{
    public function __construct(
        private readonly GisEngine $gis,
        private readonly AuditLogger $audit,
        private readonly RoadLineRepository $lines,
    ) {}

    public function saveRoadGeometry(Road $road, LineString $line, string $source = 'drawn', ?string $remarks = null): RoadGeometry
    {
        return DB::transaction(function () use ($road, $line, $source, $remarks) {
            $road = Road::query()->lockForUpdate()->findOrFail($road->id);
            $previous = $road->currentGeometry()->first();

            RoadGeometry::where('road_id', $road->id)->where('is_current', true)->update(['is_current' => false]);

            $geometry = RoadGeometry::create([
                'road_id' => $road->id,
                'version' => (int) RoadGeometry::where('road_id', $road->id)->max('version') + 1,
                'geojson' => json_encode($line->toGeoJson()),
                'length_m' => (int) round($line->length()),
                'source' => $source,
                'is_current' => true,
                'remarks' => $remarks,
                'created_by' => Auth::id(),
            ]);

            $road->length_m = $geometry->length_m;
            if ($road->end_chainage_m <= $road->start_chainage_m) {
                $road->end_chainage_m = $road->start_chainage_m + $geometry->length_m;
            }
            $road->save();

            $this->lines->forget($road->id);
            $this->gis->indexLine(GisFeature::TYPE_ROAD, $road->id, $line);
            $this->rebuildSectionGeometries($road, $line);

            $this->audit->log('road.geometry_saved', $road,
                ['version' => $previous?->version],
                ['version' => $geometry->version, 'length_m' => $geometry->length_m, 'source' => $source],
                $remarks,
            );

            return $geometry;
        });
    }

    /**
     * Re-cut every section of the road from the given (or current) road line.
     */
    public function rebuildSectionGeometries(Road $road, ?LineString $line = null): void
    {
        $line ??= $this->currentLine($road);
        if ($line === null) {
            return;
        }

        foreach ($road->sections()->get() as $section) {
            $this->cutSection($road, $section, $line);
        }
    }

    public function cutSection(Road $road, RoadSection $section, ?LineString $line = null): void
    {
        $line ??= $this->currentLine($road);
        if ($line === null) {
            return;
        }

        $from = $this->chainageToMeasure($road, $section->start_chainage_m, $line);
        $to = $this->chainageToMeasure($road, $section->end_chainage_m, $line);

        if ($to - $from < 1 || $from >= $line->length()) {
            // Section lies beyond the drawn geometry: keep it, but without geometry or index.
            $section->forceFill(['geojson' => null])->saveQuietly();
            $this->gis->remove(GisFeature::TYPE_SECTION, $section->id);

            return;
        }

        $sectionLine = $line->slice($from, $to);
        $section->forceFill(['geojson' => json_encode($sectionLine->toGeoJson())])->saveQuietly();
        $this->gis->indexLine(GisFeature::TYPE_SECTION, $section->id, $sectionLine);
        $this->gis->setActive(GisFeature::TYPE_SECTION, $section->id, $section->status === 'active');
    }

    public function currentLine(Road $road): ?LineString
    {
        $geometry = $road->currentGeometry()->first();

        return $geometry ? LineString::fromGeoJson($geometry->geojson) : null;
    }

    /** Distance along the drawn line for a chainage, honouring km-stone calibration markers. */
    public function chainageToMeasure(Road $road, int $chainageM, ?LineString $line = null): float
    {
        $line ??= $this->currentLine($road);
        if ($line === null) {
            return (float) ($chainageM - $road->start_chainage_m);
        }

        $markers = $road->chainageMarkers()->get(['chainage_m', 'latitude', 'longitude'])->toArray();

        return ChainageScale::build($line, $road->start_chainage_m, $markers)->measureAt($chainageM);
    }
}
