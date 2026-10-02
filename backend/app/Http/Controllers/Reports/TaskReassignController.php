<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Delegation\ReassignmentService;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use App\Models\WorkflowAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskReassignController extends Controller
{
    public function store(Request $request, Report $report, ReassignmentService $service): RedirectResponse
    {
        Gate::authorize('view', $report);
        $data = $request->validate([
            'assignment_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $assignment = WorkflowAssignment::where('is_active', true)
            ->whereHas('instance', fn ($q) => $q->where('report_id', $report->id))
            ->findOrFail($data['assignment_id']);

        $new = $service->manual($request->user(), $assignment, User::findOrFail($data['user_id']), $data['reason']);

        return redirect()->route('reports.show', $report)->with('success', 'Task reassigned to '.User::find($new->user_id)->name.'.');
    }
}
