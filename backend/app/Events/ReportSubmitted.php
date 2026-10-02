<?php

namespace App\Events;

use App\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A report has been accepted with its evidence. The workflow engine starts from here (Phase 6).
 */
class ReportSubmitted
{
    use Dispatchable;

    public function __construct(public readonly Report $report) {}
}
