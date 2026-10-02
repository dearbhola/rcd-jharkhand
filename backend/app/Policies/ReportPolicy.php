<?php

namespace App\Policies;

use App\Models\Evidence;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function view(User $user, Report $report): bool
    {
        return Report::visibleTo($user)->whereKey($report->id)->exists();
    }

    public function viewEvidence(User $user, Evidence $evidence): bool
    {
        return Report::visibleTo($user)->whereKey($evidence->report_id)->exists();
    }

    /** Unwatermarked originals: a separate permission on top of report visibility. */
    public function viewOriginalEvidence(User $user, Evidence $evidence): bool
    {
        return $user->can('evidence.view_original') && $this->viewEvidence($user, $evidence);
    }
}
