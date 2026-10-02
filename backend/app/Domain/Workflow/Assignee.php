<?php

namespace App\Domain\Workflow;

use App\Models\User;
use App\Models\WorkflowAssignment;

/**
 * Who will hold a task and why (primary mapping or a delegation standing in for someone).
 */
final class Assignee
{
    public function __construct(
        public readonly User $user,
        public readonly string $via = WorkflowAssignment::VIA_PRIMARY,
        public readonly ?int $delegationId = null,
        public readonly ?int $originalUserId = null,
    ) {}
}
