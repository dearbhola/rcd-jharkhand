<?php

namespace App\Domain\Workflow;

/**
 * A precondition for a transition. Throws ValidationException / AuthorizationException to refuse.
 */
interface Guard
{
    public function check(TransitionContext $ctx): void;
}
