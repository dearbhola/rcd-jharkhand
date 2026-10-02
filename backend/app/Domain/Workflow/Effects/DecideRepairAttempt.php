<?php

namespace App\Domain\Workflow\Effects;

use App\Domain\Workflow\Effect;
use App\Domain\Workflow\TransitionContext;
use App\Models\RepairAttempt;
use LogicException;

/** Records the review outcome on the latest repair attempt (accept at JE/AE, reject at any stage, final approval). */
class DecideRepairAttempt implements Effect
{
    public function __construct(private readonly string $decision) {}

    public function apply(TransitionContext $ctx): void
    {
        $attempt = RepairAttempt::withoutGlobalScopes()->where('report_id', $ctx->report->id)->orderByDesc('attempt_no')->first()
            ?? throw new LogicException('No repair attempt to decide.');

        $data = match ($this->decision) {
            'accept' => ['outcome' => $ctx->actorRole === 'AE' ? RepairAttempt::OUTCOME_ACCEPTED_AE : RepairAttempt::OUTCOME_ACCEPTED_JE],
            'approve' => ['outcome' => RepairAttempt::OUTCOME_APPROVED, 'decided_at' => now()],
            'reject' => [
                'outcome' => RepairAttempt::OUTCOME_REJECTED,
                'rejected_stage' => $ctx->actorRole,
                'rejection_reason' => $ctx->input->comment,
                'rejected_by' => $ctx->actor?->id,
                'decided_at' => now(),
            ],
        };

        $attempt->update($data);
        $ctx->attempt = $attempt;
    }
}
