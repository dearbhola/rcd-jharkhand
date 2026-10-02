<?php

namespace App\Domain\Responsibility;

use App\Domain\Gis\LocationMatch;
use App\Domain\Masters\ResponsibilityService;
use App\Models\ContractRoadSection;
use Carbon\CarbonInterface;

/**
 * Resolves contractor and JE/AE/EE for a located point. Workflow routing uses this;
 * it never trusts IDs supplied by the client.
 */
class ResponsibilityResolver
{
    public function __construct(private readonly ResponsibilityService $responsibility) {}

    public function resolve(LocationMatch $location, ?CarbonInterface $at = null): Resolution
    {
        $at ??= now();

        $mapping = ContractRoadSection::covering($location->road->id, $location->chainageM, $at)
            ->with('contract.contractor')
            ->orderByDesc('effective_from')
            ->first();

        $contract = $mapping?->contract;
        $people = $location->asset
            ? $this->responsibility->forAsset($location->asset, $at)
            : $this->responsibility->forSection($location->section, $at);

        return new Resolution(
            location: $location,
            at: $at,
            mapping: $mapping,
            contract: $contract,
            contractor: $contract?->contractor,
            maintenanceActive: (bool) $contract?->isMaintenanceActiveOn($at),
            je: $people['JE'],
            ae: $people['AE'],
            ee: $people['EE'],
        );
    }
}
