<?php

namespace App\Domain\Delegation;

use App\Domain\Workflow\Assignee;
use App\Models\Delegation;
use App\Models\User;
use App\Models\WorkflowAssignment;
use Carbon\CarbonInterface;

/**
 * Replaces an absent officer by their delegate for a role, following at most a few hops
 * (A on leave → B, B also on leave → C). The permanent mapping is never changed.
 */
class DelegationResolver
{
    private const MAX_HOPS = 3;

    public function resolve(User $user, string $roleCode, ?CarbonInterface $at = null): Assignee
    {
        $at ??= now();
        $current = $user;
        $delegationId = null;
        $seen = [$user->id];

        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            $delegation = $this->inForce($current, $roleCode, $at);
            if (! $delegation || in_array($delegation->delegate_user_id, $seen, true)) {
                break;
            }
            $delegate = User::find($delegation->delegate_user_id);
            if (! $delegate || ! $delegate->isActive()) {
                break;
            }
            $current = $delegate;
            $delegationId = $delegation->id;
            $seen[] = $delegate->id;
        }

        return $current->is($user)
            ? new Assignee($user)
            : new Assignee($current, WorkflowAssignment::VIA_DELEGATION, $delegationId, $user->id);
    }

    public function inForce(User $user, string $roleCode, CarbonInterface $at): ?Delegation
    {
        return Delegation::withoutGlobalScopes()
            ->where('primary_user_id', $user->id)
            ->where('role_code', $roleCode)
            ->inForceAt($at)
            ->orderByDesc('starts_at')
            ->first();
    }
}
