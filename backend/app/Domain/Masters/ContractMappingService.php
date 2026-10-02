<?php

namespace App\Domain\Masters;

use App\Domain\Audit\AuditLogger;
use App\Models\Contract;
use App\Models\ContractRoadSection;
use App\Models\Road;
use App\Models\RoadSection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maps contracts to road chainage ranges for a period.
 * Rule: one contractor per road chainage at any time — no two active mappings on the
 * same road may overlap in both chainage and dates. Serialised by locking the road row.
 * Mappings are never deleted: they are ended (effective_to) or cancelled with a reason.
 */
class ContractMappingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array{road_id: int, road_section_id?: ?int, start_chainage_m?: ?int, end_chainage_m?: ?int, effective_from: string, effective_to?: ?string, remarks?: ?string} $data */
    public function add(Contract $contract, array $data): ContractRoadSection
    {
        return DB::transaction(function () use ($contract, $data) {
            $road = Road::lockForUpdate()->findOrFail($data['road_id']);
            [$start, $end, $sectionId] = $this->resolveRange($road, $data);

            $from = Carbon::parse($data['effective_from'])->startOfDay();
            $to = ! empty($data['effective_to']) ? Carbon::parse($data['effective_to'])->startOfDay() : null;
            if ($to && $to->lt($from)) {
                throw ValidationException::withMessages(['effective_to' => 'Effective-to must be on or after effective-from.']);
            }

            $this->assertNoConflict($road->id, $start, $end, $from, $to);

            $mapping = ContractRoadSection::create([
                'contract_id' => $contract->id,
                'road_id' => $road->id,
                'road_section_id' => $sectionId,
                'start_chainage_m' => $start,
                'end_chainage_m' => $end,
                'effective_from' => $from->toDateString(),
                'effective_to' => $to?->toDateString(),
                'status' => ContractRoadSection::STATUS_ACTIVE,
                'remarks' => $data['remarks'] ?? null,
                'is_test' => $contract->is_test,
            ]);

            $this->audit->log('contract.mapped', $contract, null, [
                'mapping_id' => $mapping->id, 'road' => $road->code, 'chainage_m' => [$start, $end],
                'effective_from' => $mapping->effective_from->toDateString(), 'effective_to' => $mapping->effective_to?->toDateString(),
            ]);

            return $mapping;
        });
    }

    /** End a mapping on a date (history preserved). */
    public function end(ContractRoadSection $mapping, string $effectiveTo, string $reason): void
    {
        DB::transaction(function () use ($mapping, $effectiveTo, $reason) {
            $mapping = ContractRoadSection::lockForUpdate()->findOrFail($mapping->id);
            $to = Carbon::parse($effectiveTo)->startOfDay();

            if ($to->lt($mapping->effective_from)) {
                throw ValidationException::withMessages(['effective_to' => 'End date cannot be before the mapping started.']);
            }
            if ($mapping->effective_to && $to->gt($mapping->effective_to)) {
                throw ValidationException::withMessages(['effective_to' => 'A mapping can only be shortened. Add a new mapping to extend coverage.']);
            }

            $old = ['effective_to' => $mapping->effective_to?->toDateString()];
            $mapping->update(['effective_to' => $to->toDateString(), 'remarks' => trim(($mapping->remarks ? $mapping->remarks.' | ' : '').'Ended: '.$reason)]);
            $this->audit->log('contract.mapping_ended', $mapping->contract, $old, ['mapping_id' => $mapping->id, 'effective_to' => $to->toDateString()], $reason);
        });
    }

    /** @return array{0: int, 1: int, 2: ?int} */
    private function resolveRange(Road $road, array $data): array
    {
        $section = null;
        if (! empty($data['road_section_id'])) {
            $section = RoadSection::where('road_id', $road->id)->find($data['road_section_id'])
                ?? throw ValidationException::withMessages(['road_section_id' => 'The section does not belong to this road.']);
        }

        $start = $data['start_chainage_m'] ?? $section?->start_chainage_m ?? $road->start_chainage_m;
        $end = $data['end_chainage_m'] ?? $section?->end_chainage_m ?? $road->end_chainage_m;

        if ($end <= $start) {
            throw ValidationException::withMessages(['end_chainage_m' => 'End chainage must be greater than start chainage.']);
        }
        $min = $section?->start_chainage_m ?? $road->start_chainage_m;
        $max = $section?->end_chainage_m ?? $road->end_chainage_m;
        if ($start < $min || $end > $max) {
            throw ValidationException::withMessages(['start_chainage_m' => 'Chainage range must lie within the '.($section ? 'section' : 'road').'.']);
        }

        return [(int) $start, (int) $end, $section?->id];
    }

    private function assertNoConflict(int $roadId, int $start, int $end, Carbon $from, ?Carbon $to): void
    {
        $conflict = ContractRoadSection::query()
            ->withoutGlobalScopes() // test and production mappings must not overlap either
            ->with('contract:id,contract_no')
            ->where('road_id', $roadId)
            ->where('status', ContractRoadSection::STATUS_ACTIVE)
            ->where('start_chainage_m', '<', $end)
            ->where('end_chainage_m', '>', $start)
            ->whereDate('effective_from', '<=', $to ?? '9999-12-31')
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
            ->first();

        if ($conflict) {
            throw ValidationException::withMessages(['road_id' => sprintf(
                'Overlaps contract %s on km %s – %s (from %s%s). End that mapping first.',
                $conflict->contract?->contract_no ?? '#'.$conflict->contract_id,
                number_format($conflict->start_chainage_m / 1000, 3), number_format($conflict->end_chainage_m / 1000, 3),
                $conflict->effective_from->format('d-M-Y'), $conflict->effective_to ? ' to '.$conflict->effective_to->format('d-M-Y') : '',
            )]);
        }
    }
}
