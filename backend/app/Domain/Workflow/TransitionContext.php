<?php

namespace App\Domain\Workflow;

use App\Models\Inspection;
use App\Models\RepairAttempt;
use App\Models\Report;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Model;

/**
 * Mutable state shared by guards and effects during one transition.
 */
final class TransitionContext
{
    /** @var list<array<string, mixed>> evidence validated by a guard, stored by an effect */
    public array $evidence = [];

    /** @var list<string> */
    public array $flags = [];

    public ?float $distanceFromSiteM = null;

    public ?WorkflowAction $action = null;

    public ?RepairAttempt $attempt = null;

    public ?Inspection $inspection = null;

    /** Where stored evidence belongs (attempt, inspection, or the report itself). */
    public ?Model $evidenceOwner = null;

    /** Set when an effect moved the report to a different workflow; remaining effects are skipped. */
    public ?WorkflowInstance $redirectedTo = null;

    /** @var list<User> users newly assigned by this transition (for notifications) */
    public array $newAssignees = [];

    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly Report $report,
        public readonly WorkflowTransition $transition,
        public readonly WorkflowStep $from,
        public readonly WorkflowStep $to,
        public readonly ?User $actor,
        public readonly ?string $actorRole,
        public readonly TransitionInput $input,
    ) {}

    public function isSystem(): bool
    {
        return $this->actor === null;
    }

    public function halted(): bool
    {
        return $this->redirectedTo !== null;
    }
}
