<?php

namespace App\Domain\Workflow\Guards;

use App\Domain\Reporting\ReportStatus;
use App\Domain\Workflow\Guard;
use App\Domain\Workflow\TransitionContext;
use App\Models\Report;
use Illuminate\Validation\ValidationException;

/** Merging needs an open master report other than this one. */
class DuplicateTargetPresent implements Guard
{
    public function check(TransitionContext $ctx): void
    {
        $id = $ctx->input->duplicateOfReportId;
        $target = $id ? Report::find($id) : null;

        if (! $target || $target->is($ctx->report)) {
            throw ValidationException::withMessages(['duplicate_of' => 'Choose the original report this one duplicates.']);
        }
        if (in_array($target->status, [ReportStatus::INVALID_CLOSED, ReportStatus::MERGED_DUPLICATE], true)) {
            throw ValidationException::withMessages(['duplicate_of' => 'The chosen report is itself closed as invalid or merged.']);
        }
    }
}
