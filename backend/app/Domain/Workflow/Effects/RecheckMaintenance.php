<?php

namespace App\Domain\Workflow\Effects;

use App\Domain\Audit\AuditLogger;
use App\Domain\Workflow\AssignmentManager;
use App\Domain\Workflow\Effect;
use App\Domain\Workflow\TransitionContext;
use App\Domain\Workflow\WorkflowStarter;
use App\Models\ReportResponsibility;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;

/**
 * Decision D4: contractor responsibility applies only while the maintenance period is
 * active. If it has lapsed when work would go (back) to the contractor, the report moves
 * to the department workflow. A new responsibility row records the change; the old one is kept.
 */
class RecheckMaintenance implements Effect
{
    public function __construct(
        private readonly AssignmentManager $assignments,
        private readonly WorkflowStarter $starter,
        private readonly AuditLogger $audit,
    ) {}

    public function apply(TransitionContext $ctx): void
    {
        $current = $ctx->report->currentResponsibility;
        $contract = $current?->contract;

        if ($current && $current->route === ReportResponsibility::ROUTE_CONTRACTOR && $contract?->isMaintenanceActiveOn(now())) {
            return; // still the contractor's responsibility
        }

        $current?->update(['is_current' => false]);
        ReportResponsibility::create([
            'report_id' => $ctx->report->id,
            'contract_id' => $current?->contract_id,
            'contract_road_section_id' => $current?->contract_road_section_id,
            'contractor_id' => null,
            'maintenance_active' => false,
            'je_user_id' => $current?->je_user_id,
            'ae_user_id' => $current?->ae_user_id,
            'ee_user_id' => $current?->ee_user_id,
            'route' => ReportResponsibility::ROUTE_DEPARTMENT,
            'reason' => 'maintenance_expired',
            'is_current' => true,
            'resolved_at' => now(),
        ]);
        $ctx->report->unsetRelation('currentResponsibility');

        $this->assignments->endActive($ctx->instance, 'rerouted');
        $ctx->instance->update(['status' => WorkflowInstance::STATUS_SUPERSEDED, 'completed_at' => now()]);

        $new = $this->starter->startAt($ctx->report, WorkflowDefinition::DEPARTMENT, 'DEPARTMENT_PENDING', $ctx->instance);
        $ctx->redirectedTo = $new;

        $this->audit->log('workflow.rerouted', $ctx->report,
            ['route' => 'contractor', 'instance_id' => $ctx->instance->id],
            ['route' => 'department', 'instance_id' => $new->id, 'reason' => 'maintenance_expired'],
            'Maintenance period no longer active', $ctx->actor,
        );
    }
}
