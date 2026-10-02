<?php

namespace App\Domain\Masters;

use App\Domain\Audit\AuditLogger;
use App\Enums\RoleCode;
use App\Models\Asset;
use App\Models\ResponsibilityAssignment;
use App\Models\RoadSection;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permanent JE/AE/EE mapping with history. Assigning closes the row in force
 * (effective_to = day before) and inserts a new one; nothing is overwritten.
 */
class ResponsibilityService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $scopeIds
     * @return int number of scopes changed
     */
    public function assign(User $actor, string $scopeType, array $scopeIds, RoleCode $role, User $user, string $effectiveFrom, ?string $remarks = null): int
    {
        if (! $role->isEngineer()) {
            throw ValidationException::withMessages(['role_code' => 'Only JE, AE and EE are mapped to roads.']);
        }
        if (! $user->hasRole($role) || ! $user->isActive()) {
            throw ValidationException::withMessages(['user_id' => "The selected user must be an active {$role->value}."]);
        }

        $from = Carbon::parse($effectiveFrom)->startOfDay();
        $changed = 0;

        DB::transaction(function () use ($actor, $scopeType, $scopeIds, $role, $user, $from, $remarks, &$changed) {
            foreach (array_unique($scopeIds) as $scopeId) {
                $scope = $this->scopeModel($scopeType, $scopeId);

                $rows = ResponsibilityAssignment::forScope($scopeType, $scopeId)
                    ->where('role_code', $role)
                    ->lockForUpdate()
                    ->orderByDesc('effective_from')
                    ->get();

                $latest = $rows->first();
                if ($latest && $latest->effective_from->gte($from)) {
                    throw ValidationException::withMessages(['effective_from' => sprintf(
                        '%s %s already has a %s assignment starting %s. The new one must start later.',
                        $scopeType === ResponsibilityAssignment::SCOPE_SECTION ? 'Section' : 'Asset',
                        $scope->code, $role->value, $latest->effective_from->format('d-M-Y'),
                    )]);
                }
                if ($latest && $latest->user_id === $user->id && $latest->effective_to === null) {
                    continue; // already assigned, nothing to change
                }

                if ($latest && ($latest->effective_to === null || $latest->effective_to->gte($from))) {
                    $latest->update(['effective_to' => $from->copy()->subDay()->toDateString(), 'ended_by' => $actor->id]);
                }

                $new = ResponsibilityAssignment::create([
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'role_code' => $role,
                    'user_id' => $user->id,
                    'effective_from' => $from->toDateString(),
                    'remarks' => $remarks,
                    'is_test' => (bool) $scope->is_test,
                    'created_by' => $actor->id,
                ]);

                $this->audit->log('responsibility.assigned', $scope,
                    ['role' => $role->value, 'user_id' => $latest?->user_id],
                    ['role' => $role->value, 'user_id' => $user->id, 'effective_from' => $from->toDateString(), 'assignment_id' => $new->id],
                    $remarks,
                );
                $changed++;
            }
        });

        return $changed;
    }

    /**
     * JE/AE/EE in force for a section on a date.
     *
     * @return array<string, ?User> keyed by role code
     */
    public function forSection(RoadSection $section, ?CarbonInterface $at = null): array
    {
        return $this->forScope(ResponsibilityAssignment::SCOPE_SECTION, $section->id, $at);
    }

    /**
     * For an asset: asset-scoped overrides win per role; otherwise its section's mapping.
     *
     * @return array<string, ?User>
     */
    public function forAsset(Asset $asset, ?CarbonInterface $at = null): array
    {
        $own = $this->forScope(ResponsibilityAssignment::SCOPE_ASSET, $asset->id, $at);
        $section = $asset->road_section_id ? $this->forScope(ResponsibilityAssignment::SCOPE_SECTION, $asset->road_section_id, $at) : [];

        return collect(RoleCode::engineerRoles())
            ->mapWithKeys(fn (RoleCode $r) => [$r->value => $own[$r->value] ?? $section[$r->value] ?? null])
            ->all();
    }

    /** @return array<string, ?User> */
    private function forScope(string $scopeType, int $scopeId, ?CarbonInterface $at): array
    {
        $rows = ResponsibilityAssignment::forScope($scopeType, $scopeId)
            ->effectiveOn($at ?? now())
            ->with('user')
            ->get()
            ->keyBy(fn ($r) => $r->role_code->value);

        return collect(RoleCode::engineerRoles())
            ->mapWithKeys(fn (RoleCode $r) => [$r->value => $rows->get($r->value)?->user])
            ->all();
    }

    private function scopeModel(string $scopeType, int $scopeId): RoadSection|Asset
    {
        return match ($scopeType) {
            ResponsibilityAssignment::SCOPE_SECTION => RoadSection::findOrFail($scopeId),
            ResponsibilityAssignment::SCOPE_ASSET => Asset::findOrFail($scopeId),
            default => throw ValidationException::withMessages(['scope_type' => 'Invalid scope.']),
        };
    }
}
