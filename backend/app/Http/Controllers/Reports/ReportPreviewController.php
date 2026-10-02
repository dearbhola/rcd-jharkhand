<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\DuplicateDetector;
use App\Domain\Reporting\LocationValidator;
use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Live feedback for the report form: "you are on road X at km Y" (or not), and
 * possible duplicates for the chosen category. Nothing is stored.
 */
class ReportPreviewController extends Controller
{
    public function preview(Request $request, LocationValidator $validator, Settings $settings): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
            'override' => ['nullable', 'boolean'],
        ]);

        try {
            ['match' => $m, 'flags' => $flags, 'override' => $override] = $validator->validateReport(
                $request->user(), (float) $data['lat'], (float) $data['lng'], (float) $data['accuracy'], now(), $request->boolean('override'),
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()]);
        }

        return response()->json([
            'ok' => true,
            'override' => $override,
            'road' => ['code' => $m->road->code, 'name' => $m->road->name],
            'section' => $m->section->code,
            'chainage_km' => km($m->chainageM),
            'distance_m' => round($m->distanceM),
            'asset' => $m->asset ? ['type_id' => $m->asset->asset_type_id, 'type' => $m->asset->type?->name, 'name' => $m->asset->name, 'code' => $m->asset->code] : null,
            'flags' => $flags,
        ]);
    }

    public function duplicates(Request $request, DuplicateDetector $detector): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'category_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $near = $detector->near((float) $data['lat'], (float) $data['lng'], (int) $data['category_id']);

        return response()->json([
            'count' => $near->count(),
            // Reporters may not see other people's reports; only say that one exists.
            'items' => $near->filter(fn ($d) => $user->can('view', $d['report']))->map(fn ($d) => [
                'report_no' => $d['report']->report_no,
                'distance_m' => round($d['distance_m']),
                'reported' => Carbon::parse($d['report']->created_at)->diffForHumans(),
                'url' => route('reports.show', $d['report']),
            ])->values(),
        ]);
    }
}
