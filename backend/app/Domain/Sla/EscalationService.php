<?php

namespace App\Domain\Sla;

use App\Domain\Audit\AuditLogger;
use App\Domain\Workflow\AssigneeResolver;
use App\Domain\Workflow\WorkflowNotifier;
use App\Models\Escalation;
use App\Models\EscalationRule;
use App\Models\Report;
use App\Models\SlaInstance;
use App\Models\WorkflowAssignment;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Periodic SLA scan (every few minutes via the scheduler):
 *  - records breaches on running timers that passed their due time;
 *  - fires escalation rules: reminders before due, escalations after due (to the mapped AE/EE,
 *    delegation-aware) — each rule fires at most once per timer (unique key), so the scan is idempotent.
 */
class EscalationService
{
    public function __construct(
        private readonly AssigneeResolver $assignees,
        private readonly WorkflowNotifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{breached: int, fired: int} */
    public function scan(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $rules = EscalationRule::where('is_active', true)->orderBy('level')->get();
        $maxLead = (float) $rules->where('trigger', EscalationRule::TRIGGER_BEFORE_DUE)->max('offset_hours');
        $breached = $fired = 0;

        SlaInstance::withoutGlobalScopes()
            ->whereNull('completed_at')
            ->where('due_at', '<=', $now->copy()->addMinutes((int) ceil($maxLead * 60)))
            ->with('escalations:id,sla_instance_id,escalation_rule_id')
            ->chunkById(200, function (Collection $timers) use ($rules, $now, &$breached, &$fired) {
                foreach ($timers as $sla) {
                    if ($sla->breached_at === null && $now->gt($sla->due_at)) {
                        $sla->update(['breached_at' => $sla->due_at]);
                        $breached++;
                    }

                    $done = $sla->escalations->pluck('escalation_rule_id')->all();
                    foreach ($rules as $rule) {
                        if (in_array($rule->id, $done, true) || ! $this->applies($rule, $sla, $now)) {
                            continue;
                        }
                        $fired += $this->fire($rule, $sla, $now) ? 1 : 0;
                    }
                }
            });

        return compact('breached', 'fired');
    }

    private function applies(EscalationRule $rule, SlaInstance $sla, CarbonInterface $now): bool
    {
        if ($rule->stage && $rule->stage !== $sla->stage) {
            return false;
        }

        $offset = (int) round($rule->offset_hours * 60);

        if ($rule->trigger === EscalationRule::TRIGGER_BEFORE_DUE) {
            $remindAt = $sla->due_at->copy()->subMinutes($offset);

            // Skip reminders that would fire at (or before) the timer's start: the SLA is shorter than the lead time.
            return $remindAt->gt($sla->started_at) && $now->gte($remindAt) && $now->lt($sla->due_at);
        }

        return $now->gte($sla->due_at->copy()->addMinutes($offset));
    }

    private function fire(EscalationRule $rule, SlaInstance $sla, CarbonInterface $now): bool
    {
        return DB::transaction(function () use ($rule, $sla, $now) {
            $report = Report::withoutGlobalScopes()->with(['currentResponsibility', 'section', 'asset'])->find($sla->report_id);
            if (! $report) {
                return false;
            }

            $holders = WorkflowAssignment::with('user')->where('workflow_instance_id', $sla->workflow_instance_id)
                ->where('is_active', true)->get()->pluck('user')->filter();
            $escalateTo = $rule->notify_role_code
                ? collect($this->assignees->assigneesFor($report, $rule->notify_role_code))->pluck('user')
                : collect();
            $recipients = $holders->when(! $rule->notify_assignee, fn () => collect())->merge($escalateTo)->unique('id')->values();

            try {
                $escalation = Escalation::create([
                    'sla_instance_id' => $sla->id,
                    'escalation_rule_id' => $rule->id,
                    'level' => $rule->level,
                    'notified_user_id' => $escalateTo->first()?->id ?? $recipients->first()?->id,
                    'fired_at' => $now,
                ]);
            } catch (QueryException) {
                return false; // another scan fired it concurrently (unique key)
            }

            $stage = str_replace('_', ' ', $sla->stage);
            if ($rule->action === EscalationRule::ACTION_REMIND) {
                $this->notifier->send($recipients, 'sla.approaching', $report,
                    "{$report->report_no}: {$stage} is due {$sla->due_at->diffForHumans($now)} ({$sla->due_at->format('d-M H:i')}).");
            } else {
                $late = $sla->due_at->diffForHumans($now, true);
                $this->notifier->send($recipients, $rule->level > 1 ? 'sla.escalated' : 'sla.breached', $report,
                    "{$report->report_no}: {$stage} is overdue by {$late}".($rule->notify_role_code ? " — escalated to {$rule->notify_role_code} (level {$rule->level})" : '').'.');
            }

            $this->audit->log("sla.{$rule->action}", $report, null, [
                'stage' => $sla->stage, 'level' => $rule->level, 'due_at' => $sla->due_at->toDateTimeString(),
                'notified' => $recipients->pluck('id')->all(), 'escalation_id' => $escalation->id,
            ], null, null);

            return true;
        });
    }
}
