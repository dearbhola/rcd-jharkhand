<?php

namespace App\Domain\Workflow\Guards;

use App\Domain\Workflow\Guard;
use App\Domain\Workflow\TransitionContext;
use App\Models\WorkflowAssignment;
use Illuminate\Auth\Access\AuthorizationException;

/** Only a current holder of the task may act on it. */
class AssigneeIsActor implements Guard
{
    public function check(TransitionContext $ctx): void
    {
        if ($ctx->isSystem()) {
            return;
        }

        $holds = WorkflowAssignment::where('workflow_instance_id', $ctx->instance->id)
            ->where('workflow_step_id', $ctx->from->id)
            ->where('user_id', $ctx->actor->id)
            ->where('is_active', true)
            ->exists();

        if (! $holds) {
            throw new AuthorizationException('This task is not assigned to you.');
        }
    }
}
