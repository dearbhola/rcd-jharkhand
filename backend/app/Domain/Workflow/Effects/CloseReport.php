<?php

namespace App\Domain\Workflow\Effects;

use App\Domain\Workflow\Effect;
use App\Domain\Workflow\TransitionContext;

class CloseReport implements Effect
{
    public function apply(TransitionContext $ctx): void
    {
        $ctx->report->closed_at = now();
    }
}
