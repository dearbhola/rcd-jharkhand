<?php

namespace App\Domain\Workflow\Effects;

use App\Domain\Workflow\Effect;
use App\Domain\Workflow\TransitionContext;
use App\Models\ReportLink;

/** Links this report to its original; both reports and their evidence are kept. */
class LinkDuplicate implements Effect
{
    public function apply(TransitionContext $ctx): void
    {
        ReportLink::create([
            'report_id' => $ctx->report->id,
            'linked_report_id' => $ctx->input->duplicateOfReportId,
            'link_type' => ReportLink::DUPLICATE_OF,
            'reason' => $ctx->input->comment,
            'created_by' => $ctx->actor?->id,
        ]);
    }
}
