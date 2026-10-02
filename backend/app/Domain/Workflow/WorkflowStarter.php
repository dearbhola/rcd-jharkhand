<?php

namespace App\Domain\Workflow;

use App\Domain\Sla\SlaService;
use App\Models\Report;
use App\Models\ReportResponsibility;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;

/**
 * Creates workflow instances: on report submission (initial step) or when a report is
 * rerouted into another definition at a given step.
 */
class WorkflowStarter
{
    public function __construct(
        private readonly AssignmentManager $assignments,
        private readonly AssigneeResolver $assignees,
        private readonly SlaService $sla,
        private readonly WorkflowNotifier $notifier,
    ) {}

    public function start(Report $report): WorkflowInstance
    {
        $code = $report->currentResponsibility?->route === ReportResponsibility::ROUTE_CONTRACTOR
            ? WorkflowDefinition::CONTRACTOR_MAINTENANCE
            : WorkflowDefinition::DEPARTMENT;

        return $this->startAt($report, $code, null);
    }

    public function startAt(Report $report, string $definitionCode, ?string $stepCode, ?WorkflowInstance $supersedes = null): WorkflowInstance
    {
        $definition = WorkflowDefinition::activeCode($definitionCode)->firstOrFail();
        $step = WorkflowStep::where('workflow_definition_id', $definition->id)
            ->when($stepCode, fn ($q) => $q->where('code', $stepCode), fn ($q) => $q->where('is_initial', true))
            ->firstOrFail();

        $instance = WorkflowInstance::create([
            'report_id' => $report->id,
            'workflow_definition_id' => $definition->id,
            'current_step_id' => $step->id,
            'status' => WorkflowInstance::STATUS_ACTIVE,
            'version' => 1,
            'started_at' => now(),
            'is_test' => $report->is_test,
        ]);
        $supersedes?->update(['superseded_by_id' => $instance->id]);

        $assignees = $this->assignees->assigneesFor($report, $step->actor_role_code);
        $users = array_map(fn (Assignee $a) => $a->user, $assignees);
        if ($assignees === []) {
            // Accepted reports are never lost: an unassigned task is visible to administrators.
            report_flag($report, 'responsibility_gap');
        } else {
            $this->assignments->assign($instance, $step, $assignees, $step->actor_role_code);
            $this->notifier->send($users, 'task.assigned', $report, "{$report->report_no}: {$step->name} — action required.");
        }

        if ($step->sla_stage) {
            $this->sla->start($instance, $report, $step->sla_stage, $users[0]->id ?? null, null);
        }

        $report->forceFill(['status' => $step->code])->save();

        return $instance;
    }
}
