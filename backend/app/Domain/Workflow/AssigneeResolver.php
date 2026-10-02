<?php

namespace App\Domain\Workflow;

use App\Domain\Delegation\DelegationResolver;
use App\Domain\Masters\ResponsibilityService;
use App\Enums\RoleCode;
use App\Models\Report;
use App\Models\User;

/**
 * Who should hold a step: the officer currently mapped to the report's road section
 * (or asset), or the active users of the responsible contractor.
 * The report's responsibility snapshot is the fallback when no current mapping exists.
 * If the mapped officer is on leave, their delegate is returned instead.
 */
class AssigneeResolver
{
    public function __construct(
        private readonly ResponsibilityService $responsibility,
        private readonly DelegationResolver $delegations,
    ) {}

    /** @return list<Assignee> */
    public function assigneesFor(Report $report, string $roleCode): array
    {
        $users = $this->forRole($report, $roleCode);

        return $roleCode === RoleCode::CONTRACTOR->value
            ? array_map(fn (User $u) => new Assignee($u), $users)
            : array_map(fn (User $u) => $this->delegations->resolve($u, $roleCode), $users);
    }

    /** The mapped holder(s) before delegation. @return list<User> */
    public function forRole(Report $report, string $roleCode): array
    {
        $report->loadMissing(['currentResponsibility', 'section', 'asset']);

        if ($roleCode === RoleCode::CONTRACTOR->value) {
            $contractorId = $report->currentResponsibility?->contractor_id;

            return $contractorId
                ? User::where('contractor_id', $contractorId)->where('status', User::STATUS_ACTIVE)
                    ->whereHas('roles', fn ($q) => $q->where('code', RoleCode::CONTRACTOR->value))->get()->all()
                : [];
        }

        $people = $report->asset
            ? $this->responsibility->forAsset($report->asset)
            : $this->responsibility->forSection($report->section);
        $user = $people[$roleCode] ?? null;

        if (! $user) {
            $snapshot = $report->currentResponsibility;
            $id = match ($roleCode) {
                'JE' => $snapshot?->je_user_id, 'AE' => $snapshot?->ae_user_id, 'EE' => $snapshot?->ee_user_id, default => null,
            };
            $user = $id ? User::find($id) : null;
        }

        return $user && $user->isActive() ? [$user] : [];
    }
}
