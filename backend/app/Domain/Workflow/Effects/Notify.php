<?php

namespace App\Domain\Workflow\Effects;

use App\Domain\Reporting\ReportStatus;
use App\Domain\Workflow\Effect;
use App\Domain\Workflow\TransitionContext;
use App\Domain\Workflow\WorkflowNotifier;
use App\Models\User;

/** mode: assignees | reporter | contractor */
class Notify implements Effect
{
    public function __construct(private readonly WorkflowNotifier $notifier, private readonly string $mode) {}

    public function apply(TransitionContext $ctx): void
    {
        $r = $ctx->report;
        $label = ReportStatus::labels()[$ctx->to->code][0] ?? $ctx->to->code;
        $reason = $ctx->transition->requires_reason && $ctx->input->comment ? " Reason: {$ctx->input->comment}" : '';

        match ($this->mode) {
            'assignees' => $this->notifier->send($ctx->newAssignees, 'task.assigned', $r,
                "{$r->report_no}: {$ctx->to->name} — action required.{$reason}", $ctx->actor, $ctx->to->code),
            'reporter' => $this->notifier->send([User::find($r->reporter_id)], 'report.status', $r,
                "Your report {$r->report_no} is now: {$label}.{$reason}", $ctx->actor, $ctx->to->code),
            'contractor' => $this->notifier->send(
                User::where('contractor_id', $ctx->report->currentResponsibility?->contractor_id ?? 0)->where('status', 'active')->get(),
                'repair.approved', $r, "{$r->report_no}: repair approved by EE. Task closed.", $ctx->actor, $ctx->to->code),
        };
    }
}
