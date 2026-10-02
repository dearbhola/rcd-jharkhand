<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Workflow\TransitionInput;
use App\Domain\Workflow\WorkflowEngine;
use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Performs a workflow action on a report. All business rules live in the WorkflowEngine;
 * this only translates the HTTP request.
 */
class ReportActionController extends Controller
{
    public function store(Request $request, Report $report, string $action, WorkflowEngine $engine): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $report);

        $data = $request->validate([
            'version' => ['required', 'integer'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'repair_description' => ['nullable', 'string', 'max:2000'],
            'duplicate_of' => ['nullable', 'integer'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'captured_at' => ['nullable', 'date'],
            'location_override' => ['nullable', 'boolean'],
            'evidence' => ['nullable', 'array', 'max:20'],
            'evidence.*' => ['file'],
        ]);

        $done = $engine->transition($report, $action, $request->user(), new TransitionInput(
            comment: isset($data['comment']) ? trim($data['comment']) : null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            accuracyM: isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            capturedAt: isset($data['captured_at']) ? Carbon::parse($data['captured_at'])->setTimezone(config('app.timezone')) : null,
            files: $request->file('evidence', []),
            repairDescription: $data['repair_description'] ?? null,
            duplicateOfReportId: $data['duplicate_of'] ?? null,
            locationOverride: $request->boolean('location_override'),
            ip: $request->ip(),
        ), (int) $data['version']);

        $message = ($done->loadMissing('transition')->transition?->name ?? ucfirst(str_replace('_', ' ', $action))).' — done.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'url' => route('reports.show', $report)]);
        }

        return redirect()->route('reports.show', $report)->with('success', $message);
    }
}
