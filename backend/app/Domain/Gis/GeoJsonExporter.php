<?php

namespace App\Domain\Gis;

use App\Models\Road;

/**
 * GeoJSON FeatureCollections for exchange with other GIS systems (WGS84 / EPSG:4326).
 */
class GeoJsonExporter
{
    /** @return list<array<string, mixed>> features: the road line, then its sections */
    public function roadFeatures(Road $road): array
    {
        $features = [];
        $geometry = $road->currentGeometry;

        if ($geometry) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($geometry->geojson, true),
                'properties' => [
                    'feature' => 'road',
                    'road_code' => $road->code,
                    'road_name' => $road->name,
                    'road_number' => $road->road_number,
                    'category' => $road->category?->code,
                    'division' => $road->division?->code,
                    'start_chainage_m' => $road->start_chainage_m,
                    'end_chainage_m' => $road->end_chainage_m,
                    'length_m' => $geometry->length_m,
                    'geometry_version' => $geometry->version,
                    'external_ref' => $road->external_ref,
                ],
            ];
        }

        foreach ($road->sections as $section) {
            if (! $section->geojson) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($section->geojson, true),
                'properties' => [
                    'feature' => 'road_section',
                    'road_code' => $road->code,
                    'section_code' => $section->code,
                    'start_chainage_m' => $section->start_chainage_m,
                    'end_chainage_m' => $section->end_chainage_m,
                    'sub_division' => $section->subDivision?->code,
                ],
            ];
        }

        return $features;
    }

    /** @param iterable<Road> $roads */
    public function collection(iterable $roads): array
    {
        $features = [];
        foreach ($roads as $road) {
            array_push($features, ...$this->roadFeatures($road));
        }

        return [
            'type' => 'FeatureCollection',
            'name' => 'rcd_roads',
            'crs' => ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:OGC:1.3:CRS84']],
            'generated_at' => now()->toIso8601String(),
            'features' => $features,
        ];
    }
}
