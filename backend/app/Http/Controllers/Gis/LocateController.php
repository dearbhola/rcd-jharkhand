<?php

namespace App\Http\Controllers\Gis;

use App\Domain\Gis\LocationResolver;
use App\Domain\Responsibility\ResponsibilityResolver;
use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * "What does the system resolve here?" — point → road, section, chainage, contract,
 * contractor, JE/AE/EE and workflow route. Diagnostic tool for administrators and the
 * same resolution that field reports use (Phase 5).
 */
class LocateController extends Controller
{
    public function index(Settings $settings): View
    {
        return view('gis.locate', [
            'mapConfig' => GeometryController::mapConfig($settings),
            'defaultRadius' => $settings->int('report.location_radius_m'),
        ]);
    }

    public function resolve(Request $request, LocationResolver $locator, ResponsibilityResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'between:5,5000'],
            'date' => ['nullable', 'date'],
        ]);

        $radius = $data['radius'] ?? app(Settings::class)->int('report.location_radius_m');
        $at = isset($data['date']) ? Carbon::parse($data['date']) : now();
        $matches = $locator->candidates((float) $data['lat'], (float) $data['lng'], $radius);

        if ($matches->isEmpty()) {
            return response()->json([
                'found' => false,
                'radius_m' => $radius,
                'message' => 'You are not currently within the permitted reporting area.',
            ]);
        }

        return response()->json([
            'found' => true,
            'radius_m' => $radius,
            'match' => $resolver->resolve($matches->first(), $at)->toArray(),
            'alternatives' => $matches->slice(1)->map->toArray()->values(),
        ]);
    }
}
