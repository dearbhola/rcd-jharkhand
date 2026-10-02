<?php

namespace App\Domain\Workflow;

use App\Domain\Audit\AuditLogger;
use App\Domain\Evidence\EvidenceService;
use App\Models\Report;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowAssignment;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Executes workflow transitions defined in the database.
 *
 * Every transition: one DB transaction; the instance row is locked and its version
 * compared with what the actor saw (optimistic concurrency → WorkflowConflict);
 * role + permission + guards checked; an append-only WorkflowAction written; effects
 * applied in order; report status updated; audited. Notifications go out after commit.
 */
class WorkflowEngine
{
    /** Permission required per action (in addition to the role allowed by the transition). */
    private const PERMISSIONS = [
        'validate' => 'report.validate',
        'invalidate' => 'report.validate',
        'merge_duplicate' => 'report.link_duplicate',
        'acknowledge' => 'repair.submit',
        'submit_repair' => 'repair.submit',
        'accept' => 'report.review',
        'approve' => 'approval.approve',
        'close' => 'report.view_all',
    ];

    public function __construct(
        private readonly Registry $registry,
        private readonly EvidenceService $evidence,
        private readonly AuditLogger $audit,
    ) {}

    public function activeInstance(Report $report): ?WorkflowInstance
    {
        return WorkflowInstance::withoutGlobalScopes()->where('report_id', $report->id)->where('status', WorkflowInstance::STATUS_ACTIVE)->first();
    }

    /**
     * @param  ?User  $actor  null = system (automatic transition; role, permission and actor guards are skipped)
     */
    public function transition(Report $report, string $actionCode, ?User $actor, TransitionInput $input, ?int $expectedVersion = null): WorkflowAction
    {
        return DB::transaction(function () use ($report, $actionCode, $actor, $input, $expectedVersion) {
            $instance = WorkflowInstance::withoutGlobalScopes()
                ->where('report_id', $report->id)
                ->where('status', WorkflowInstance::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $instance || ($expectedVersion !== null && $instance->version !== $expectedVersion)) {
                throw new WorkflowConflict;
            }

            $report = Report::withoutGlobalScopes()->with(['currentResponsibility.contract', 'section', 'asset'])->findOrFail($report->id);
            $transition = WorkflowTransition::with(['fromStep', 'toStep'])
                ->where('from_step_id', $instance->current_step_id)
                ->where('action_code', $actionCode)
                ->first();

            if (! $transition) {
                throw $expectedVersion !== null ? new WorkflowConflict : ValidationException::withMessages(['action' => 'This action is not available at the current stage.']);
            }

            $role = $actor ? $this->actorRole($transition, $actor) : null;
            $this->assertReason($transition, $input);

            $ctx = new TransitionContext($instance, $report, $transition, $transition->fromStep, $transition->toStep, $actor, $role, $input);

            foreach ($transition->guards ?? [] as $key) {
                $this->registry->guard($key)->check($ctx);
            }

            $ctx->action = WorkflowAction::create([
                'workflow_instance_id' => $instance->id,
                'workflow_transition_id' => $transition->id,
                'action_code' => $actionCode,
                'from_step_id' => $transition->from_step_id,
                'to_step_id' => $transition->to_step_id,
                'actor_id' => $actor?->id,
                'actor_role_code' => $role,
                'comment' => $input->comment,
                'latitude' => $input->latitude,
                'longitude' => $input->longitude,
                'gps_accuracy_m' => $input->accuracyM,
                'payload' => array_filter([
                    'flags' => $ctx->flags ?: null,
                    'distance_from_site_m' => $ctx->distanceFromSiteM !== null ? round($ctx->distanceFromSiteM, 1) : null,
                    'duplicate_of' => $input->duplicateOfReportId,
                    'system' => $actor === null ? true : null,
                ]) ?: null,
                'ip_address' => $input->ip,
            ]);

            foreach ($transition->effects ?? [] as $key) {
                $this->registry->effect($key)->apply($ctx);
                if ($ctx->halted()) {
                    break;
                }
            }

            $this->storeEvidence($ctx);
            $this->advance($ctx);

            $this->audit->log("workflow.{$actionCode}", $report,
                ['status' => $transition->fromStep->code],
                ['status' => $report->status, 'action_id' => $ctx->action->id, 'flags' => $ctx->flags ?: null],
                $input->comment, $actor,
            );

            return $ctx->action;
        });
    }

    /**
     * Transitions the user may perform now (for building the UI). Guards that need input
     * (location, evidence, reason) are checked on submit.
     *
     * @return Collection<int, WorkflowTransition>
     */
    public function availableActions(Report $report, User $user): Collection
    {
        $instance = $this->activeInstance($report);
        if (! $instance) {
            return collect();
        }

        $holds = WorkflowAssignment::where('workflow_instance_id', $instance->id)
            ->where('workflow_step_id', $instance->current_step_id)->where('user_id', $user->id)->where('is_active', true)->exists();
        $r = $report->currentResponsibility;
        $mapped = $r && in_array($user->id, [$r->je_user_id, $r->ae_user_id, $r->ee_user_id], true);

        return WorkflowTransition::with('toStep')
            ->where('from_step_id', $instance->current_step_id)
            ->where('is_automatic', false)
            ->orderBy('sort_order')
            ->get()
            ->filter(function (WorkflowTransition $t) use ($user, $holds, $mapped) {
                try {
                    $this->actorRole($t, $user);
                } catch (AuthorizationException) {
                    return false;
                }
                $guards = $t->guards ?? [];

                return (! in_array('assignee.is_actor', $guards, true) || $holds)
                    && (! in_array('actor.mapped_engineer', $guards, true) || $holds || $mapped);
            })
            ->values();
    }

    private function actorRole(WorkflowTransition $transition, User $actor): string
    {
        $role = collect($transition->allowed_role_codes ?? [])->first(fn ($code) => $actor->hasRole($code));
        if (! $role) {
            throw new AuthorizationException('Your role cannot perform this action.');
        }

        $permission = $transition->action_code === 'reject'
            ? ($role === 'EE' ? 'approval.reject' : 'report.reject')
            : (self::PERMISSIONS[$transition->action_code] ?? null);

        if ($permission && ! $actor->hasPermission($permission)) {
            throw new AuthorizationException('You do not have permission for this action.');
        }

        return $role;
    }

    private function assertReason(WorkflowTransition $transition, TransitionInput $input): void
    {
        if ($transition->requires_reason && mb_strlen(trim((string) $input->comment)) < 5) {
            throw ValidationException::withMessages(['comment' => 'A reason is mandatory for this action (at least 5 characters).']);
        }
    }

    private function storeEvidence(TransitionContext $ctx): void
    {
        $owner = $ctx->evidenceOwner ?? $ctx->report;
        foreach ($ctx->evidence as $item) {
            $stored = $this->evidence->store($item, $ctx->report, $owner, $ctx->actor, [
                'lat' => $ctx->input->latitude ?? $ctx->report->latitude,
                'lng' => $ctx->input->longitude ?? $ctx->report->longitude,
                'accuracy' => $ctx->input->accuracyM,
                'captured_at' => $ctx->input->capturedAt ?? now(),
            ]);
            $stored->update(['workflow_action_id' => $ctx->action->id]);
        }
    }

    private function advance(TransitionContext $ctx): void
    {
        $report = $ctx->report;

        if ($ctx->halted()) {
            $report->status = WorkflowStep::whereKey($ctx->redirectedTo->current_step_id)->value('code');
        } else {
            $terminal = $ctx->to->is_terminal;
            $ctx->instance->update([
                'current_step_id' => $ctx->to->id,
                'version' => $ctx->instance->version + 1,
                'status' => $terminal ? WorkflowInstance::STATUS_COMPLETED : WorkflowInstance::STATUS_ACTIVE,
                'completed_at' => $terminal ? now() : null,
            ]);
            $report->status = $ctx->to->code;
            if ($terminal) {
                $report->closed_at ??= now();
            }
        }

        $report->save();
    }
}
