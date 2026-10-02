<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SlaInstance;
use App\Models\WorkflowAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My tasks": everything currently assigned to the user, oldest due first.
 */
class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $assignments = WorkflowAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('instance', fn ($q) => $q->where('status', 'active'))
            ->with([
                'step:id,code,name',
                'instance.report' => fn ($q) => $q->with(['road:id,code', 'section:id,code', 'category:id,name', 'severity:id,name,color']),
            ])
            ->get()
            ->filter(fn ($a) => $a->instance?->report !== null); // test-data visibility respected

        $sla = SlaInstance::whereIn('workflow_instance_id', $assignments->pluck('workflow_instance_id'))
            ->whereNull('completed_at')->get()->keyBy('workflow_instance_id');

        $rows = $assignments->map(fn ($a) => ['assignment' => $a, 'report' => $a->instance->report, 'sla' => $sla[$a->workflow_instance_id] ?? null])
            ->sortBy(fn ($r) => $r['sla']?->due_at?->timestamp ?? PHP_INT_MAX)
            ->values();

        return view('reports.tasks', [
            'groups' => $rows->groupBy(fn ($r) => $r['assignment']->step->name),
            'total' => $rows->count(),
            'overdue' => $rows->filter(fn ($r) => $r['sla'] && $r['sla']->due_at->isPast())->count(),
        ]);
    }
}
