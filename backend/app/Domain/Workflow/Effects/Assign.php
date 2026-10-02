<?php

namespace App\Domain\Workflow\Effects;

use App\Domain\Workflow\Assignee;
use App\Domain\Workflow\AssigneeResolver;
use App\Domain\Workflow\AssignmentManager;
use App\Domain\Workflow\Effect;
use App\Domain\Workflow\TransitionContext;
use App\Enums\RoleCode;
use Illuminate\Validation\ValidationException;

/**
 * Hands the task to the next holder(s):
 *  - mode "step_role": whoever the target step's role resolves to (JE/AE/EE)
 *  - mode "contractor": the responsible contractor's active users
 *  - mode "keep": the same people continue at the new step
 *  - mode "end": nobody (terminal)
 */
class Assign implements Effect
{
    public function __construct(
        private readonly AssignmentManager $assignments,
        private readonly AssigneeResolver $resolver,
        private readonly string $mode,
    ) {}

    public function apply(TransitionContext $ctx): void
    {
        $previous = $ctx->instance->activeAssignments()->with('user')->get();
        $this->assignments->endActive($ctx->instance, $this->mode === 'end' ? 'completed' : 'handed_over');

        if ($this->mode === 'end') {
            return;
        }

        [$assignees, $role] = match ($this->mode) {
            // Same holders continue (keeping how they came to hold it, e.g. via delegation).
            'keep' => [$previous->filter(fn ($a) => $a->user)->map(fn ($a) => new Assignee($a->user, $a->assigned_via, $a->delegation_id, $a->original_user_id))->values()->all(),
                $previous->first()?->role_code ?? $ctx->to->actor_role_code],
            'contractor' => [$this->resolver->assigneesFor($ctx->report, RoleCode::CONTRACTOR->value), RoleCode::CONTRACTOR->value],
            default => [$this->resolver->assigneesFor($ctx->report, $ctx->to->actor_role_code), $ctx->to->actor_role_code],
        };

        if ($assignees === []) {
            throw ValidationException::withMessages(['assignee' => "No active {$role} is mapped for this road section. Ask an administrator to update the mapping."]);
        }

        $this->assignments->assign($ctx->instance, $ctx->to, $assignees, $role, $ctx->actor);
        $ctx->newAssignees = array_map(fn (Assignee $a) => $a->user, $assignees);
    }
}
