<?php

namespace App\Domain\Workflow;

use RuntimeException;

/**
 * The task changed since the actor loaded it (another user acted first).
 */
class WorkflowConflict extends RuntimeException
{
    public function __construct(string $message = 'This task was already processed by someone else. Reload to see its current state.')
    {
        parent::__construct($message);
    }
}
