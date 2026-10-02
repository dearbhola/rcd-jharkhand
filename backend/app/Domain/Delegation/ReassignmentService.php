<?php

namespace App\Domain\Delegation;

use App\Domain\Audit\AuditLogger;
use App\Domain\Workflow\WorkflowNotifier;
use App\Enums\RoleCode;
use App\Models\Delegation;
use App\Models\Report;
use App\Models\User;
use App\Models\WorkflowAssignment;
use App\Models\WorkflowInstance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a task from one holder to another. The old assignment is ended (never deleted);
 * the new one links back to it and to the original holder. Every move is audited and
 * bumps the workflow version, so a page opened by the previous holder can no longer act.
 */
class ReassignmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly WorkflowNotifier $notifier,
    ) {}

    public function reassign(
        WorkflowAssignment $assignment,
        User $to,
        string $via,
        string $reason,
        ?User $by = null,
        ?Delegation $delegation = null,
        string $endReason = 'reassigned',
    ): WorkflowAssignment {
        return DB::transaction(function () use ($assignment, $to, $via, $reason, $by, $delegation, $endReason) {
            $instance = WorkflowInstance::withoutGlobalScopes()->lockForUpdate()->findOrFail($assignment->workflow_instance_id);
            $assignment = WorkflowAssignment::lockForUpdate()->findOrFail($assignment->id);

            if (! $assignment->is_active || $instance->status !== WorkflowInstance::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['assignment' => 'This task is no longer open.']);
            }
            if ($assignment->user_id === $to->id) {
                throw ValidationException::withMessages(['user_id' => 'The task is already with this user.']);
            }
            if (! $to->isActive()) {
                throw ValidationException::withMessages(['user_id' => 'The selected user is not active.']);
            }

            $assignment->update(['is_active' => false, 'ended_at' => now(), 'end_reason' => $endReason]);

            $new = WorkflowAssignment::create([
                'workflow_instance_id' => $assignment->workflow_instance_id,
                'workflow_step_id' => $assignment->workflow_step_id,
                'user_id' => $to->id,
                'role_code' => $assignment->role_code,
                'assigned_via' => $via,
                'original_user_id' => $via === WorkflowAssignment::VIA_PRIMARY ? null : ($assignment->original_user_id ?? $assignment->user_id),
                'delegation_id' => $delegation?->id,
                'previous_assignment_id' => $assignment->id,
                'is_active' => true,
                'assigned_at' => now(),
                'assigned_by' => $by?->id,
            ]);

            $instance->update(['version' => $instance->version + 1]);

            $report = Report::withoutGlobalScopes()->findOrFail($instance->report_id);
            $this->audit->log('workflow.reassigned', $report,
                ['user_id' => $assignment->user_id, 'assignment_id' => $assignment->id],
                ['user_id' => $to->id, 'assignment_id' => $new->id, 'via' => $via, 'delegation_id' => $delegation?->id],
                $reason, $by,
            );
            $this->notifier->send([$to], 'task.reassigned', $report, "{$report->report_no} has been assigned to you. {$reason}");

            return $new;
        });
    }

    /**
     * Manual reassignment by a supervisor (permission workflow.reassign). The new holder must be able
     * to do the step: same engineer role, or a user of the same contractor firm.
     */
    public function manual(User $by, WorkflowAssignment $assignment, User $to, string $reason): WorkflowAssignment
    {
        if (! $by->can('workflow.reassign')) {
            throw new AuthorizationException('You cannot reassign tasks.');
        }

        $role = $assignment->role_code;
        $eligible = $role === RoleCode::CONTRACTOR->value
            ? $to->hasRole(RoleCode::CONTRACTOR) && $to->contractor_id === User::whereKey($assignment->user_id)->value('contractor_id')
            : $to->hasRole($role);

        if (! $eligible) {
            throw ValidationException::withMessages(['user_id' => $role === RoleCode::CONTRACTOR->value
                ? 'Contractor tasks can only move to another user of the same contractor.'
                : "The new holder must have the {$role} role."]);
        }

        return $this->reassign($assignment, $to, WorkflowAssignment::VIA_MANUAL, $reason, $by);
    }
}
