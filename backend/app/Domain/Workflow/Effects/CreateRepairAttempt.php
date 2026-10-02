<?php

namespace App\Domain\Workflow\Effects;

use App\Domain\Workflow\Effect;
use App\Domain\Workflow\TransitionContext;
use App\Models\RepairAttempt;
use Illuminate\Validation\ValidationException;

/** Every repair submission is a new, independently preserved attempt. */
class CreateRepairAttempt implements Effect
{
    public function apply(TransitionContext $ctx): void
    {
        $in = $ctx->input;
        if (! trim((string) $in->repairDescription)) {
            throw ValidationException::withMessages(['repair_description' => 'Describe the repair carried out.']);
        }

        $ctx->attempt = RepairAttempt::create([
            'report_id' => $ctx->report->id,
            'attempt_no' => (int) RepairAttempt::withoutGlobalScopes()->where('report_id', $ctx->report->id)->max('attempt_no') + 1,
            'contractor_id' => $ctx->actor?->contractor_id ?? $ctx->report->currentResponsibility?->contractor_id,
            'submitted_by' => $ctx->actor->id,
            'workflow_action_id' => $ctx->action->id,
            'description' => trim($in->repairDescription),
            'comments' => $in->comment,
            'latitude' => $in->latitude ?? $ctx->report->latitude,
            'longitude' => $in->longitude ?? $ctx->report->longitude,
            'gps_accuracy_m' => $in->accuracyM,
            'captured_at' => $in->capturedAt,
            'submitted_at' => now(),
            'outcome' => RepairAttempt::OUTCOME_PENDING,
            'is_test' => $ctx->report->is_test,
        ]);
        $ctx->evidenceOwner = $ctx->attempt;
    }
}
