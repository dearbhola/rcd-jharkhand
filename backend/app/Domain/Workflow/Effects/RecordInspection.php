<?php

namespace App\Domain\Workflow\Effects;

use App\Domain\Evidence\EvidenceService;
use App\Domain\Workflow\Effect;
use App\Domain\Workflow\TransitionContext;
use App\Models\Inspection;
use App\Models\RepairAttempt;

/** Records the officer's decision at validation / review / approval, with GPS and evidence. */
class RecordInspection implements Effect
{
    private const DECISIONS = ['validate' => 'valid', 'invalidate' => 'invalid', 'accept' => 'accepted', 'reject' => 'rejected', 'approve' => 'approved'];

    public function __construct(private readonly EvidenceService $evidence) {}

    public function apply(TransitionContext $ctx): void
    {
        if ($ctx->isSystem()) {
            return;
        }

        $stage = $ctx->from->code === 'PENDING_VALIDATION' ? Inspection::STAGE_VALIDATION : $ctx->from->code;
        $in = $ctx->input;

        $ctx->inspection = Inspection::create([
            'report_id' => $ctx->report->id,
            'repair_attempt_id' => $stage === Inspection::STAGE_VALIDATION ? null
                : RepairAttempt::withoutGlobalScopes()->where('report_id', $ctx->report->id)->orderByDesc('attempt_no')->value('id'),
            'workflow_action_id' => $ctx->action->id,
            'stage' => $stage,
            'inspector_id' => $ctx->actor->id,
            'inspector_role_code' => $ctx->actorRole,
            'decision' => self::DECISIONS[$ctx->transition->action_code] ?? $ctx->transition->action_code,
            'comment' => $in->comment,
            'latitude' => $in->latitude,
            'longitude' => $in->longitude,
            'gps_accuracy_m' => $in->accuracyM,
            'distance_from_site_m' => $ctx->distanceFromSiteM !== null ? round($ctx->distanceFromSiteM, 2) : null,
            'location_verified' => $ctx->distanceFromSiteM !== null && ! in_array('location_override', $ctx->flags, true),
            'location_override' => in_array('location_override', $ctx->flags, true),
            'inspected_at' => now(),
            'is_test' => $ctx->report->is_test,
        ]);
        $ctx->evidenceOwner = $ctx->inspection;
    }
}
