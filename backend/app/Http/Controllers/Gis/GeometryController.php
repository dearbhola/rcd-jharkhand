<?php

namespace App\Http\Controllers\Gis;

use App\Domain\Gis\ChainageMarkerService;
use App\Domain\Gis\GeoJsonExporter;
use App\Domain\Gis\GeometryFileParser;
use App\Domain\Gis\LineString;
use App\Domain\Gis\RoadGeometryService;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Road;
use App\Models\RoadChainageMarker;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Road geometry editor: draw/edit, import (GeoJSON/KML/GPX), export, km markers.
 */
class GeometryController extends Controller
{
    private const MAX_VERTICES = 20000;

    public function edit(Road $road, Settings $settings): View
    {
        $road->load(['currentGeometry', 'sections', 'chainageMarkers']);

        return view('gis.editor', [
            'road' => $road,
            'mapConfig' => $this->mapConfig($settings),
            'payload' => [
                'road' => ['id' => $road->id, 'code' => $road->code, 'start_m' => $road->start_chainage_m, 'end_m' => $road->end_chainage_m],
                'geometry' => $road->currentGeometry ? json_decode($road->currentGeometry->geojson, true) : null,
                'sections' => $road->sections->filter->geojson->map(fn ($s) => [
                    'code' => $s->code, 'km' => km($s->start_chainage_m).' – '.km($s->end_chainage_m), 'geometry' => json_decode($s->geojson, true),
                ])->values(),
                'markers' => $road->chainageMarkers->map(fn ($m) => [
                    'id' => $m->id, 'km' => km($m->chainage_m), 'lat' => $m->latitude, 'lng' => $m->longitude, 'label' => $m->label,
                    'delete_url' => route('gis.markers.destroy', $m),
                ]),
                'urls' => [
                    'save' => route('gis.geometry.update', $road),
                    'import' => route('gis.geometry.import', $road),
                    'markers' => route('gis.markers.store', $road),
                ],
            ],
        ]);
    }

    public function update(Request $request, Road $road, RoadGeometryService $geometry): JsonResponse
    {
        $data = $request->validate([
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', 'in:LineString'],
            'geometry.coordinates' => ['required', 'array', 'min:2', 'max:'.self::MAX_VERTICES],
            'remarks' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'in:drawn,imported'],
        ]);

        try {
            $line = LineString::fromGeoJson($data['geometry']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['geometry' => $e->getMessage()]);
        }

        $version = $geometry->saveRoadGeometry($road, $line, $data['source'] ?? 'drawn', $data['remarks'] ?? null);

        return response()->json([
            'message' => "Geometry saved as version {$version->version}.",
            'version' => $version->version,
            'length_km' => km($version->length_m),
            'warning' => $this->lengthWarning($road->fresh(), $version->length_m),
        ]);
    }

    /** Parse an uploaded file into a draft line for the editor. Nothing is saved here. */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['geojson', 'json', 'kml', 'gpx'], true)) {
            throw ValidationException::withMessages(['file' => 'Upload a .geojson, .json, .kml or .gpx file.']);
        }

        try {
            $line = app(GeometryFileParser::class)->parse($file->get(), $extension);
        } catch (InvalidArgumentException|\JsonException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        if (count($line->coordinates()) > self::MAX_VERTICES) {
            $line = $line->simplify(1.0);
        }

        return response()->json([
            'geometry' => $line->toGeoJson(),
            'vertices' => count($line->coordinates()),
            'length_km' => km((int) round($line->length())),
        ]);
    }

    public function exportRoad(Road $road, GeoJsonExporter $exporter): StreamedResponse
    {
        $road->load(['currentGeometry', 'sections.subDivision', 'category', 'division']);

        return $this->download($exporter->collection([$road]), Str::slug($road->code).'.geojson');
    }

    public function exportAll(Request $request, GeoJsonExporter $exporter): StreamedResponse
    {
        $divisionId = $request->integer('division_id') ?: null;
        $roads = Road::query()
            ->with(['currentGeometry', 'sections.subDivision', 'category', 'division'])
            ->when($divisionId, fn ($q, $v) => $q->where('division_id', $v))
            ->whereHas('currentGeometry')
            ->orderBy('code')
            ->lazy(50);

        $name = 'rcd-roads'.($divisionId ? '-'.Str::slug(Division::find($divisionId)?->code ?? 'division') : '').'-'.now()->format('Ymd').'.geojson';

        return $this->download($exporter->collection($roads), $name);
    }

    public function storeMarker(Request $request, Road $road, ChainageMarkerService $markers): JsonResponse
    {
        $data = $request->validate([
            'chainage_km' => ['required', 'numeric', 'min:0'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'label' => ['nullable', 'string', 'max:50'],
        ]);

        $marker = $markers->add($road, (int) round($data['chainage_km'] * 1000), (float) $data['latitude'], (float) $data['longitude'], $data['label'] ?? null);

        return response()->json(['message' => 'Marker added; sections re-cut.', 'id' => $marker->id], 201);
    }

    public function destroyMarker(RoadChainageMarker $marker, ChainageMarkerService $markers): JsonResponse|RedirectResponse
    {
        $markers->remove($marker);

        return response()->json(['message' => 'Marker removed; sections re-cut.']);
    }

    /** @return array<string, mixed> */
    public static function mapConfig(Settings $settings): array
    {
        return [
            'tileUrl' => $settings->get('map.tile_url'),
            'attribution' => $settings->get('map.tile_attribution'),
            'center' => $settings->get('map.default_center'),
            'zoom' => $settings->int('map.default_zoom'),
        ];
    }

    private function lengthWarning(Road $road, int $lengthM): ?string
    {
        $declared = $road->end_chainage_m - $road->start_chainage_m;
        if ($declared <= 0 || abs($lengthM - $declared) / $declared <= 0.05) {
            return null;
        }

        return sprintf('Drawn length (%s km) differs from the declared chainage (%s km) by more than 5%%. Check the alignment or add km-stone markers.',
            km($lengthM), km($declared));
    }

    private function download(array $collection, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($collection) {
            echo json_encode($collection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/geo+json']);
    }
}
