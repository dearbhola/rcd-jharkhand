<?php

namespace App\Domain\Delegation;

use App\Domain\Audit\AuditLogger;
use App\Domain\Workflow\WorkflowNotifier;
use App\Enums\RoleCode;
use App\Models\Delegation;
use App\Models\User;
use App\Models\WorkflowAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Leave / unavailability. While a delegation is in force, NEW tasks for the primary officer's
 * role go to the delegate (see DelegationResolver). Existing tasks move according to the mode:
 *   all_pending — every open task moves when the delegation starts
 *   new_only    — open tasks stay with the primary
 *   selective   — a supervisor picks which open tasks move
 * The permanent JE/AE/EE mapping is never changed.
 */
class DelegationService
{
    public function __construct(
        private readonly ReassignmentService $reassign,
        private readonly AuditLogger $audit,
        private readonly WorkflowNotifier $notifier,
    ) {}

    /** @param array{primary_user_id: int, delegate_user_id: int, role_code: string, starts_at: string, ends_at: string, reason: string, transfer_mode: string, return_on_end?: bool} $data */
    public function create(User $by, array $data): Delegation
    {
        $role = RoleCode::from($data['role_code']);
        $primary = User::findOrFail($data['primary_user_id']);
        $delegate = User::findOrFail($data['delegate_user_id']);
        $starts = Carbon::parse($data['starts_at']);
        $ends = Carbon::parse($data['ends_at']);

        if (! $role->isEngineer()) {
            throw ValidationException::withMessages(['role_code' => 'Delegation applies to JE, AE and EE duties.']);
        }
        if ($primary->is($delegate)) {
            throw ValidationException::withMessages(['delegate_user_id' => 'Choose someone else as the stand-in.']);
        }
        if (! $primary->hasRole($role)) {
            throw ValidationException::withMessages(['primary_user_id' => "{$primary->name} does not hold the {$role->value} role."]);
        }
        if (! $delegate->hasRole($role) || ! $delegate->isActive()) {
            throw ValidationException::withMessages(['delegate_user_id' => "The stand-in must be an active {$role->value}."]);
        }
        if ($ends->lte($starts)) {
            throw ValidationException::withMessages(['ends_at' => 'The end must be after the start.']);
        }

        return DB::transaction(function () use ($by, $data, $role, $primary, $delegate, $starts, $ends) {
            $overlap = $this->overlapping($primary->id, $role->value, $starts, $ends)->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_at' => "{$primary->name} already has a {$role->value} delegation in this period."]);
            }
            if ($this->overlapping($delegate->id, $role->value, $starts, $ends)->exists()) {
                throw ValidationException::withMessages(['delegate_user_id' => "{$delegate->name} is also away during this period."]);
            }

            $delegation = Delegation::create([
                'primary_user_id' => $primary->id,
                'delegate_user_id' => $delegate->id,
                'role_code' => $role,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'reason' => $data['reason'],
                'transfer_mode' => $data['transfer_mode'],
                'return_on_end' => (bool) ($data['return_on_end'] ?? true),
                'status' => Delegation::STATUS_SCHEDULED,
                'is_test' => $primary->is_test || $delegate->is_test,
                'created_by' => $by->id,
            ]);

            if ($starts->lte(now())) {
                $this->activate($delegation, $by);
            }

            return $delegation->fresh() ?? $delegation;
        });
    }

    public function activate(Delegation $delegation, ?User $by = null): void
    {
        DB::transaction(function () use ($delegation, $by) {
            $delegation = Delegation::withoutGlobalScopes()->lockForUpdate()->findOrFail($delegation->id);
            if ($delegation->status !== Delegation::STATUS_SCHEDULED) {
                return;
            }

            $delegation->update(['status' => Delegation::STATUS_ACTIVE, 'activated_at' => now()]);
            $moved = 0;

            if ($delegation->transfer_mode === Delegation::MODE_ALL_PENDING) {
                foreach ($this->pendingTasks($delegation) as $assignment) {
                    $this->moveToDelegate($delegation, $assignment, $by);
                    $moved++;
                }
            }

            $this->audit->log('delegation.activated', $delegation, null, ['transferred_tasks' => $moved], null, $by);
            $primary = User::find($delegation->primary_user_id);
            $this->notifier->system([User::find($delegation->delegate_user_id)], 'delegation.started',
                "You are standing in for {$primary?->name} ({$delegation->role_code->value}) until {$delegation->ends_at->format('d-M-Y H:i')}. {$moved} task(s) transferred.",
                route('delegations.show', $delegation, false));
            $this->notifier->system([$primary], 'delegation.started',
                "Your {$delegation->role_code->value} tasks are routed to ".User::find($delegation->delegate_user_id)?->name." until {$delegation->ends_at->format('d-M-Y H:i')}.",
                route('delegations.show', $delegation, false));
        });
    }

    /**
     * Selective mode: move chosen open tasks of the primary to the delegate.
     *
     * @param  list<int>  $assignmentIds
     */
    public function transfer(Delegation $delegation, array $assignmentIds, User $by): int
    {
        if ($delegation->status !== Delegation::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['assignments' => 'Tasks can only be transferred while the delegation is active.']);
        }

        $tasks = $this->pendingTasks($delegation)->whereIn('id', $assignmentIds);
        if ($tasks->count() !== count(array_unique($assignmentIds))) {
            throw ValidationException::withMessages(['assignments' => 'Some selected tasks are no longer open or do not belong to the officer on leave.']);
        }

        $tasks->each(fn ($a) => $this->moveToDelegate($delegation, $a, $by));

        return $tasks->count();
    }

    public function end(Delegation $delegation, ?User $by, string $reason, bool $cancel = false): void
    {
        DB::transaction(function () use ($delegation, $by, $reason, $cancel) {
            $delegation = Delegation::withoutGlobalScopes()->lockForUpdate()->findOrFail($delegation->id);
            if (! in_array($delegation->status, [Delegation::STATUS_SCHEDULED, Delegation::STATUS_ACTIVE], true)) {
                return;
            }

            $wasActive = $delegation->status === Delegation::STATUS_ACTIVE;
            $delegation->update([
                'status' => $cancel ? Delegation::STATUS_CANCELLED : Delegation::STATUS_ENDED,
                'ended_at' => now(),
                'cancelled_by' => $cancel ? $by?->id : null,
            ]);

            $returned = 0;
            if ($wasActive && $delegation->return_on_end) {
                $primary = User::find($delegation->primary_user_id);
                if ($primary?->isActive()) {
                    WorkflowAssignment::where('delegation_id', $delegation->id)->where('is_active', true)
                        ->whereHas('instance', fn ($q) => $q->where('status', 'active'))
                        ->get()
                        ->each(function ($a) use ($primary, $by, &$returned) {
                            $this->reassign->reassign($a, $primary, WorkflowAssignment::VIA_PRIMARY, 'Returned: delegation ended', $by, endReason: 'returned');
                            $returned++;
                        });
                }
            }

            $this->audit->log($cancel ? 'delegation.cancelled' : 'delegation.ended', $delegation, null, ['returned_tasks' => $returned], $reason, $by);
            if ($wasActive) {
                $this->notifier->system(User::whereIn('id', [$delegation->primary_user_id, $delegation->delegate_user_id])->get(), 'delegation.ended',
                    "Delegation of {$delegation->role_code->value} duties has ended. {$returned} open task(s) returned.", route('delegations.show', $delegation, false));
            }
        });
    }

    /** Scheduler: start due delegations, end expired ones. @return array{activated: int, ended: int} */
    public function processDue(): array
    {
        $activated = $ended = 0;
        $now = now();

        Delegation::withoutGlobalScopes()->whereIn('status', [Delegation::STATUS_SCHEDULED, Delegation::STATUS_ACTIVE])
            ->where('ends_at', '<=', $now)->get()
            ->each(function ($d) use (&$ended) {
                $this->end($d, null, 'Delegation period ended');
                $ended++;
            });

        Delegation::withoutGlobalScopes()->where('status', Delegation::STATUS_SCHEDULED)
            ->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->get()
            ->each(function ($d) use (&$activated) {
                $this->activate($d);
                $activated++;
            });

        return compact('activated', 'ended');
    }

    /** Open tasks the primary currently holds for the delegated role. @return Collection<int, WorkflowAssignment> */
    public function pendingTasks(Delegation $delegation): Collection
    {
        return WorkflowAssignment::query()
            ->with(['step:id,name', 'instance.report' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'report_no', 'road_id', 'chainage_m', 'status')])
            ->where('user_id', $delegation->primary_user_id)
            ->where('role_code', $delegation->role_code->value)
            ->where('is_active', true)
            ->whereHas('instance', fn ($q) => $q->withoutGlobalScopes()->where('status', 'active'))
            ->get();
    }

    private function moveToDelegate(Delegation $delegation, WorkflowAssignment $assignment, ?User $by): void
    {
        $this->reassign->reassign($assignment, User::findOrFail($delegation->delegate_user_id), WorkflowAssignment::VIA_DELEGATION,
            "Delegation: {$delegation->reason}", $by, $delegation, 'delegated');
    }

    private function overlapping(int $userId, string $role, Carbon $starts, Carbon $ends)
    {
        return Delegation::withoutGlobalScopes()
            ->where('primary_user_id', $userId)
            ->where('role_code', $role)
            ->whereIn('status', [Delegation::STATUS_SCHEDULED, Delegation::STATUS_ACTIVE])
            ->where('starts_at', '<', $ends)
            ->where('ends_at', '>', $starts);
    }
}
