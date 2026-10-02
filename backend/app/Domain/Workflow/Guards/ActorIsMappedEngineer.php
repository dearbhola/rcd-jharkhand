<?php

namespace App\Domain\Workflow\Guards;

use App\Domain\Workflow\Guard;
use App\Domain\Workflow\TransitionContext;
use App\Models\WorkflowAssignment;
use Illuminate\Auth\Access\AuthorizationException;

/** The actor is the report's JE/AE/EE (by snapshot) or currently holds the task. */
class ActorIsMappedEngineer implements Guard
{
    public function check(TransitionContext $ctx): void
    {
        $r = $ctx->report->currentResponsibility;
        $id = $ctx->actor?->id;

        $mapped = $id && $r && in_array($id, [$r->je_user_id, $r->ae_user_id, $r->ee_user_id], true);
        $assigned = $id && WorkflowAssignment::where('workflow_instance_id', $ctx->instance->id)->where('user_id', $id)->where('is_active', true)->exists();

        if (! $mapped && ! $assigned) {
            throw new AuthorizationException('Only the responsible JE, AE or EE can do this.');
        }
    }
}
