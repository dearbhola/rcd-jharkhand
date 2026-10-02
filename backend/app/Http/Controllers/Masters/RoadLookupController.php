<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Road;
use Illuminate\Http\JsonResponse;

/**
 * Small JSON lookups for dependent form fields (sections of a road).
 */
class RoadLookupController extends Controller
{
    public function sections(Road $road): JsonResponse
    {
        return response()->json([
            'road' => ['id' => $road->id, 'code' => $road->code, 'start_m' => $road->start_chainage_m, 'end_m' => $road->end_chainage_m],
            'sections' => $road->sections()->get(['id', 'code', 'name', 'start_chainage_m', 'end_chainage_m', 'status'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'code' => $s->code,
                    'label' => sprintf('%s (km %s – %s)', $s->code, km($s->start_chainage_m), km($s->end_chainage_m)),
                    'status' => $s->status,
                ]),
        ]);
    }
}
