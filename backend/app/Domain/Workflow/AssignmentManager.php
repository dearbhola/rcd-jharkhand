<?php

namespace App\Domain\Workflow;

use App\Models\User;
use App\Models\WorkflowAssignment;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;

/**
 * Task holders. Assignments are ended (with a reason), never deleted.
 */
class AssignmentManager
{
    public function endActive(WorkflowInstance $instance, string $reason): void
    {
        WorkflowAssignment::where('workflow_instance_id', $instance->id)->where('is_active', true)->get()
            ->each(fn (WorkflowAssignment $a) => $a->update(['is_active' => false, 'ended_at' => now(), 'end_reason' => $reason]));
    }

    /**
     * @param  list<Assignee>  $assignees
     * @return list<WorkflowAssignment>
     */
    public function assign(WorkflowInstance $instance, WorkflowStep $step, array $assignees, string $roleCode, ?User $by = null): array
    {
        return array_map(fn (Assignee $a) => WorkflowAssignment::create([
            'workflow_instance_id' => $instance->id,
            'workflow_step_id' => $step->id,
            'user_id' => $a->user->id,
            'role_code' => $roleCode,
            'assigned_via' => $a->via,
            'original_user_id' => $a->originalUserId,
            'delegation_id' => $a->delegationId,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by' => $by?->id,
        ]), $assignees);
    }
}
