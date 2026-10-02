<?php

namespace App\Domain\Workflow;

/**
 * A consequence of a transition, run inside the transition's database transaction.
 */
interface Effect
{
    public function apply(TransitionContext $ctx): void;
}
