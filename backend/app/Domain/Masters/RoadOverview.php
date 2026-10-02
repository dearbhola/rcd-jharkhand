<?php

namespace App\Domain\Masters;

use App\Enums\RoleCode;
use App\Models\ContractRoadSection;
use App\Models\ResponsibilityAssignment;
use App\Models\Road;
use App\Models\RoadSection;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read model for a road's sections with the contractor and JE/AE/EE in force on a date.
 * Loads everything in a fixed number of queries.
 */
class RoadOverview
{
    /**
     * @return Collection<int, array{section: RoadSection, mappings: Collection, maintenance_active: bool, people: array<string, ?User>}>
     */
    public function sections(Road $road, CarbonInterface $at): Collection
    {
        $sections = $road->sections()->get();

        $mappings = ContractRoadSection::query()
            ->with('contract.contractor:id,name,code')
            ->where('road_id', $road->id)
            ->where('status', ContractRoadSection::STATUS_ACTIVE)
            ->whereDate('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $at))
            ->get();

        $assignments = ResponsibilityAssignment::query()
            ->with('user:id,name,employee_code,mobile')
            ->where('scope_type', ResponsibilityAssignment::SCOPE_SECTION)
            ->whereIn('scope_id', $sections->pluck('id'))
            ->effectiveOn($at)
            ->get()
            ->groupBy('scope_id');

        return $sections->map(function ($section) use ($mappings, $assignments, $at) {
            $covering = $mappings->filter(fn ($m) => $m->start_chainage_m < $section->end_chainage_m && $m->end_chainage_m > $section->start_chainage_m)->values();
            $rows = ($assignments[$section->id] ?? collect())->keyBy(fn ($r) => $r->role_code->value);

            return [
                'section' => $section,
                'mappings' => $covering,
                'maintenance_active' => $covering->contains(fn ($m) => $m->contract->isMaintenanceActiveOn($at)),
                'people' => collect(RoleCode::engineerRoles())->mapWithKeys(fn ($r) => [$r->value => $rows->get($r->value)?->user])->all(),
            ];
        });
    }
}
