<?php

namespace App\Domain\Sla;

use App\Models\Report;
use App\Models\SlaInstance;
use App\Models\SlaRule;
use App\Models\WorkflowInstance;

/**
 * Starts and stops SLA timers per workflow stage. The most specific active rule wins
 * (category > asset type > severity). Hours are copied onto the timer so later rule
 * edits never rewrite history. Escalation scanning is Phase 8.
 */
class SlaService
{
    public function start(WorkflowInstance $instance, Report $report, string $stage, ?int $responsibleUserId, ?int $contractorId): ?SlaInstance
    {
        $rule = $this->ruleFor($report, $stage);
        if (! $rule) {
            return null;
        }

        $now = now();

        return SlaInstance::create([
            'workflow_instance_id' => $instance->id,
            'report_id' => $report->id,
            'stage' => $stage,
            'sla_rule_id' => $rule->id,
            'hours' => $rule->hours,
            'responsible_user_id' => $responsibleUserId,
            'contractor_id' => $contractorId,
            'started_at' => $now,
            'due_at' => $now->copy()->addMinutes((int) round($rule->hours * 60)),
            'is_test' => $report->is_test,
        ]);
    }

    public function closeOpen(WorkflowInstance $instance): void
    {
        $now = now();
        SlaInstance::withoutGlobalScopes()
            ->where('workflow_instance_id', $instance->id)
            ->whereNull('completed_at')
            ->get()
            ->each(fn (SlaInstance $sla) => $sla->update([
                'completed_at' => $now,
                'breached_at' => $sla->breached_at ?? ($now->gt($sla->due_at) ? $sla->due_at : null),
            ]));
    }

    public function ruleFor(Report $report, string $stage): ?SlaRule
    {
        return SlaRule::query()
            ->where('stage', $stage)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('asset_type_id')->orWhere('asset_type_id', $report->asset_type_id))
            ->where(fn ($q) => $q->whereNull('issue_category_id')->orWhere('issue_category_id', $report->issue_category_id))
            ->where(fn ($q) => $q->whereNull('severity_id')->orWhere('severity_id', $report->severity_id))
            ->get()
            ->sortByDesc(fn (SlaRule $r) => $r->specificity())
            ->first();
    }
}
