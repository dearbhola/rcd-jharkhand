<?php

namespace Database\Seeders;

use App\Models\SlaRule;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Version 1 of the two workflow definitions (approved decisions D2–D4):
 *
 *  CONTRACTOR_MAINTENANCE: validation → contractor repair → JE review → AE review → EE approval.
 *      Any review rejection reopens the task to the contractor while maintenance is active;
 *      otherwise the `route.recheck_maintenance` effect moves the report to DEPARTMENT.
 *  DEPARTMENT: placeholder until the department flow is specified.
 *
 * Guard/effect keys are resolved by the WorkflowEngine registry (Phase 6).
 * Idempotent: an existing definition version is left untouched.
 */
class WorkflowDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->define(WorkflowDefinition::CONTRACTOR_MAINTENANCE, 'Contractor maintenance repair', 'Report within an active maintenance period.', $this->contractorSteps(), $this->contractorTransitions());
            $this->define(WorkflowDefinition::DEPARTMENT, 'Department action (placeholder)', 'No contract or maintenance period expired. Detailed flow to be specified.', $this->departmentSteps(), $this->departmentTransitions());
        });
    }

    /**
     * @param  list<array{0: string, 1: string, 2: ?string, 3: ?string, 4?: string}>  $steps  [code, name, actorRole, slaStage, flag]
     * @param  list<array<string, mixed>>  $transitions
     */
    private function define(string $code, string $name, string $description, array $steps, array $transitions): void
    {
        if (WorkflowDefinition::where('code', $code)->where('version', 1)->exists()) {
            return;
        }

        $definition = WorkflowDefinition::create(['code' => $code, 'version' => 1, 'name' => $name, 'description' => $description]);

        $ids = [];
        foreach ($steps as $i => $s) {
            $ids[$s[0]] = WorkflowStep::create([
                'workflow_definition_id' => $definition->id,
                'code' => $s[0],
                'name' => $s[1],
                'actor_role_code' => $s[2],
                'sla_stage' => $s[3],
                'is_initial' => ($s[4] ?? null) === 'initial',
                'is_terminal' => ($s[4] ?? null) === 'terminal',
                'sort_order' => $i,
            ])->id;
        }

        foreach ($transitions as $i => $t) {
            WorkflowTransition::create([
                'workflow_definition_id' => $definition->id,
                'from_step_id' => $ids[$t['from']],
                'to_step_id' => $ids[$t['to']],
                'action_code' => $t['action'],
                'name' => $t['name'],
                'allowed_role_codes' => $t['roles'] ?? null,
                'requires_reason' => $t['reason'] ?? false,
                'requires_evidence' => $t['evidence'] ?? false,
                'requires_location' => $t['location'] ?? false,
                'guards' => $t['guards'] ?? [],
                'effects' => $t['effects'] ?? [],
                'is_automatic' => $t['auto'] ?? false,
                'sort_order' => $i,
            ]);
        }
    }

    private function contractorSteps(): array
    {
        return [
            ['PENDING_VALIDATION', 'Awaiting JE validation', 'JE', SlaRule::STAGE_VALIDATION, 'initial'],
            ['ASSIGNED', 'Assigned to contractor', 'CONTRACTOR', SlaRule::STAGE_RESPONSE],
            ['IN_PROGRESS', 'Repair in progress', 'CONTRACTOR', SlaRule::STAGE_REPAIR],
            ['REOPENED', 'Reopened for repair', 'CONTRACTOR', SlaRule::STAGE_REPAIR],
            ['JE_REVIEW', 'Awaiting JE field review', 'JE', SlaRule::STAGE_JE_REVIEW],
            ['AE_REVIEW', 'Awaiting AE field review', 'AE', SlaRule::STAGE_AE_REVIEW],
            ['EE_APPROVAL', 'Forwarded to EE for approval', 'EE', SlaRule::STAGE_EE_APPROVAL],
            ['CLOSED', 'Approved and closed', null, null, 'terminal'],
            ['INVALID_CLOSED', 'Closed as invalid', null, null, 'terminal'],
            ['MERGED_DUPLICATE', 'Merged as duplicate', null, null, 'terminal'],
        ];
    }

    private function contractorTransitions(): array
    {
        $submit = fn (string $from) => [
            'from' => $from, 'to' => 'JE_REVIEW', 'action' => 'submit_repair', 'name' => 'Submit repair',
            'roles' => ['CONTRACTOR'], 'evidence' => true, 'location' => true,
            'guards' => ['assignee.is_actor', 'location.within_repair_radius', 'evidence.repair_requirements'],
            'effects' => ['repair_attempt.create', 'sla.close_current', 'assign.step_role', 'sla.start', 'notify.assignees', 'notify.reporter'],
        ];
        $acknowledge = fn (string $from) => [
            'from' => $from, 'to' => 'IN_PROGRESS', 'action' => 'acknowledge', 'name' => 'Start repair',
            'roles' => ['CONTRACTOR'], 'guards' => ['assignee.is_actor'],
            'effects' => ['sla.close_current', 'assign.keep', 'sla.start', 'notify.reporter'],
        ];
        $reject = fn (string $from, string $role, array $extraGuards = []) => [
            'from' => $from, 'to' => 'REOPENED', 'action' => 'reject', 'name' => 'Reject repair',
            'roles' => [$role], 'reason' => true,
            'evidence' => $role !== 'EE', 'location' => $role !== 'EE',
            'guards' => ['assignee.is_actor', ...$extraGuards],
            'effects' => ['inspection.record', 'repair_attempt.reject', 'sla.close_current', 'route.recheck_maintenance', 'assign.contractor', 'sla.start', 'notify.assignees', 'notify.reporter'],
        ];

        return [
            [
                'from' => 'PENDING_VALIDATION', 'to' => 'ASSIGNED', 'action' => 'validate', 'name' => 'Validate report',
                'roles' => ['JE'], 'guards' => ['assignee.is_actor', 'location.validation_if_required'],
                'effects' => ['inspection.record', 'sla.close_current', 'route.recheck_maintenance', 'assign.contractor', 'sla.start', 'notify.assignees', 'notify.reporter'],
            ],
            [
                'from' => 'PENDING_VALIDATION', 'to' => 'INVALID_CLOSED', 'action' => 'invalidate', 'name' => 'Close as invalid',
                'roles' => ['JE'], 'reason' => true, 'guards' => ['assignee.is_actor'],
                'effects' => ['inspection.record', 'sla.close_current', 'assign.end_all', 'report.close', 'notify.reporter'],
            ],
            [
                'from' => 'PENDING_VALIDATION', 'to' => 'MERGED_DUPLICATE', 'action' => 'merge_duplicate', 'name' => 'Merge as duplicate',
                'roles' => ['JE'], 'reason' => true, 'guards' => ['assignee.is_actor', 'duplicate.target_present'],
                'effects' => ['report.link_duplicate', 'sla.close_current', 'assign.end_all', 'report.close', 'notify.reporter'],
            ],
            $acknowledge('ASSIGNED'),
            $acknowledge('REOPENED'),
            $submit('ASSIGNED'),
            $submit('IN_PROGRESS'),
            $submit('REOPENED'),
            [
                'from' => 'JE_REVIEW', 'to' => 'AE_REVIEW', 'action' => 'accept', 'name' => 'Accept repair (JE)',
                'roles' => ['JE'], 'evidence' => true, 'location' => true,
                'guards' => ['assignee.is_actor', 'location.within_review_radius', 'evidence.inspection_requirements'],
                'effects' => ['inspection.record', 'repair_attempt.accept', 'sla.close_current', 'assign.step_role', 'sla.start', 'notify.assignees'],
            ],
            $reject('JE_REVIEW', 'JE', ['location.within_review_radius', 'evidence.inspection_requirements']),
            [
                'from' => 'AE_REVIEW', 'to' => 'EE_APPROVAL', 'action' => 'accept', 'name' => 'Accept repair (AE)',
                'roles' => ['AE'], 'evidence' => true, 'location' => true,
                'guards' => ['assignee.is_actor', 'location.within_review_radius', 'evidence.inspection_requirements'],
                'effects' => ['inspection.record', 'repair_attempt.accept', 'sla.close_current', 'assign.step_role', 'sla.start', 'notify.assignees'],
            ],
            $reject('AE_REVIEW', 'AE', ['location.within_review_radius', 'evidence.inspection_requirements']),
            [
                'from' => 'EE_APPROVAL', 'to' => 'CLOSED', 'action' => 'approve', 'name' => 'Final approval',
                'roles' => ['EE'], 'guards' => ['assignee.is_actor'],
                'effects' => ['inspection.record', 'repair_attempt.approve', 'sla.close_current', 'assign.end_all', 'report.close', 'notify.contractor', 'notify.reporter'],
            ],
            $reject('EE_APPROVAL', 'EE'),
        ];
    }

    private function departmentSteps(): array
    {
        return [
            ['PENDING_VALIDATION', 'Awaiting JE validation', 'JE', SlaRule::STAGE_VALIDATION, 'initial'],
            ['DEPARTMENT_PENDING', 'Pending department action', 'JE', null],
            ['CLOSED', 'Closed', null, null, 'terminal'],
            ['INVALID_CLOSED', 'Closed as invalid', null, null, 'terminal'],
            ['MERGED_DUPLICATE', 'Merged as duplicate', null, null, 'terminal'],
        ];
    }

    private function departmentTransitions(): array
    {
        return [
            [
                'from' => 'PENDING_VALIDATION', 'to' => 'DEPARTMENT_PENDING', 'action' => 'validate', 'name' => 'Validate report',
                'roles' => ['JE'], 'guards' => ['assignee.is_actor', 'location.validation_if_required'],
                'effects' => ['inspection.record', 'sla.close_current', 'assign.step_role', 'notify.assignees', 'notify.reporter'],
            ],
            [
                'from' => 'PENDING_VALIDATION', 'to' => 'INVALID_CLOSED', 'action' => 'invalidate', 'name' => 'Close as invalid',
                'roles' => ['JE'], 'reason' => true, 'guards' => ['assignee.is_actor'],
                'effects' => ['inspection.record', 'sla.close_current', 'assign.end_all', 'report.close', 'notify.reporter'],
            ],
            [
                'from' => 'PENDING_VALIDATION', 'to' => 'MERGED_DUPLICATE', 'action' => 'merge_duplicate', 'name' => 'Merge as duplicate',
                'roles' => ['JE'], 'reason' => true, 'guards' => ['assignee.is_actor', 'duplicate.target_present'],
                'effects' => ['report.link_duplicate', 'sla.close_current', 'assign.end_all', 'report.close', 'notify.reporter'],
            ],
            [
                'from' => 'DEPARTMENT_PENDING', 'to' => 'CLOSED', 'action' => 'close', 'name' => 'Close (department)',
                'roles' => ['JE', 'AE', 'EE'], 'reason' => true, 'guards' => ['actor.mapped_engineer'],
                'effects' => ['assign.end_all', 'report.close', 'notify.reporter'],
            ],
        ];
    }
}
