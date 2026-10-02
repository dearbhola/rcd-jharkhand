<?php

namespace App\Domain\Workflow\Effects;

use App\Domain\Sla\SlaService;
use App\Domain\Workflow\Effect;
use App\Domain\Workflow\TransitionContext;

/** mode "start": timer for the target step's SLA stage; mode "close": stop running timers. */
class Sla implements Effect
{
    public function __construct(private readonly SlaService $sla, private readonly string $mode) {}

    public function apply(TransitionContext $ctx): void
    {
        if ($this->mode === 'close') {
            $this->sla->closeOpen($ctx->instance);

            return;
        }

        if ($ctx->to->sla_stage) {
            $this->sla->start($ctx->instance, $ctx->report, $ctx->to->sla_stage,
                $ctx->newAssignees[0]->id ?? null,
                $ctx->to->actor_role_code === 'CONTRACTOR' ? $ctx->report->currentResponsibility?->contractor_id : null);
        }
    }
}
