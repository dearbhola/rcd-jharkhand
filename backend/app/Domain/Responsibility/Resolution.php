<?php

namespace App\Domain\Responsibility;

use App\Domain\Gis\LocationMatch;
use App\Models\Contract;
use App\Models\Contractor;
use App\Models\ContractRoadSection;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Who is responsible for a located point on a date (§4 chain):
 * road → section → chainage → active contract → maintenance period → contractor → JE → AE → EE → route.
 */
final class Resolution
{
    public const ROUTE_CONTRACTOR = 'contractor';

    public const ROUTE_DEPARTMENT = 'department';

    public function __construct(
        public readonly LocationMatch $location,
        public readonly CarbonInterface $at,
        public readonly ?ContractRoadSection $mapping,
        public readonly ?Contract $contract,
        public readonly ?Contractor $contractor,
        public readonly bool $maintenanceActive,
        public readonly ?User $je,
        public readonly ?User $ae,
        public readonly ?User $ee,
    ) {}

    public function route(): string
    {
        return $this->maintenanceActive ? self::ROUTE_CONTRACTOR : self::ROUTE_DEPARTMENT;
    }

    /** @return list<string> missing JE/AE/EE mapping, which blocks workflow routing */
    public function gaps(): array
    {
        return array_keys(array_filter(['JE' => $this->je, 'AE' => $this->ae, 'EE' => $this->ee], fn ($u) => $u === null));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $person = fn (?User $u) => $u ? ['id' => $u->id, 'name' => $u->name, 'employee_code' => $u->employee_code, 'mobile' => $u->mobile] : null;

        return [
            ...$this->location->toArray(),
            'at' => $this->at->toDateString(),
            'contract' => $this->contract ? [
                'id' => $this->contract->id, 'contract_no' => $this->contract->contract_no,
                'maintenance_start' => $this->contract->maintenance_start_date?->toDateString(),
                'maintenance_end' => $this->contract->maintenance_end_date?->toDateString(),
            ] : null,
            'contractor' => $this->contractor ? ['id' => $this->contractor->id, 'name' => $this->contractor->name] : null,
            'maintenance_active' => $this->maintenanceActive,
            'route' => $this->route(),
            'je' => $person($this->je),
            'ae' => $person($this->ae),
            'ee' => $person($this->ee),
            'gaps' => $this->gaps(),
        ];
    }
}
