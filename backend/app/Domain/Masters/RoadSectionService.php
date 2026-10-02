<?php

namespace App\Domain\Masters;

use App\Domain\Audit\AuditLogger;
use App\Domain\Gis\GisEngine;
use App\Domain\Gis\RoadGeometryService;
use App\Models\GisFeature;
use App\Models\ResponsibilityAssignment;
use App\Models\Road;
use App\Models\RoadSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sections partition a road by chainage. Rules: inside the road's chainage range,
 * no overlap with another section of the same road. Geometry is cut from the road line.
 */
class RoadSectionService
{
    public function __construct(
        private readonly RoadGeometryService $geometry,
        private readonly GisEngine $gis,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Road $road, array $data): RoadSection
    {
        return DB::transaction(function () use ($road, $data) {
            $road = Road::lockForUpdate()->findOrFail($road->id);
            $this->assertValidRange($road, (int) $data['start_chainage_m'], (int) $data['end_chainage_m']);

            $section = RoadSection::create([
                ...$data,
                'road_id' => $road->id,
                'length_m' => $data['end_chainage_m'] - $data['start_chainage_m'],
                'is_test' => $road->is_test,
            ]);
            $this->geometry->cutSection($road, $section);

            return $section;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(RoadSection $section, array $data): RoadSection
    {
        return DB::transaction(function () use ($section, $data) {
            $road = Road::lockForUpdate()->findOrFail($section->road_id);
            $this->assertValidRange($road, (int) $data['start_chainage_m'], (int) $data['end_chainage_m'], $section->id);

            $section->update([...$data, 'length_m' => $data['end_chainage_m'] - $data['start_chainage_m']]);
            $this->geometry->cutSection($road, $section);
            $this->gis->setActive(GisFeature::TYPE_SECTION, $section->id, $section->status === 'active');

            return $section;
        });
    }

    /**
     * Split a section at a chainage. The original keeps its id (and history) and ends at the split
     * point; the new section takes the remainder and inherits the JE/AE/EE assignments in force.
     */
    public function split(RoadSection $section, int $atChainageM, string $newCode, ?string $newName = null): RoadSection
    {
        return DB::transaction(function () use ($section, $atChainageM, $newCode, $newName) {
            $road = Road::lockForUpdate()->findOrFail($section->road_id);
            $section = RoadSection::lockForUpdate()->findOrFail($section->id);

            if ($atChainageM <= $section->start_chainage_m || $atChainageM >= $section->end_chainage_m) {
                throw ValidationException::withMessages(['split_chainage_m' => 'Split point must be strictly inside the section.']);
            }
            if (RoadSection::withTrashed()->where('road_id', $road->id)->where('code', $newCode)->exists()) {
                throw ValidationException::withMessages(['new_code' => 'This section code already exists on the road.']);
            }

            $originalEnd = $section->end_chainage_m;
            $section->update(['end_chainage_m' => $atChainageM, 'length_m' => $atChainageM - $section->start_chainage_m]);

            $new = RoadSection::create([
                'road_id' => $road->id,
                'code' => $newCode,
                'name' => $newName,
                'division_id' => $section->division_id,
                'sub_division_id' => $section->sub_division_id,
                'start_chainage_m' => $atChainageM,
                'end_chainage_m' => $originalEnd,
                'length_m' => $originalEnd - $atChainageM,
                'status' => $section->status,
                'is_test' => $section->is_test,
            ]);

            ResponsibilityAssignment::forScope(ResponsibilityAssignment::SCOPE_SECTION, $section->id)
                ->effectiveOn(now())
                ->get()
                ->each(fn (ResponsibilityAssignment $a) => ResponsibilityAssignment::create([
                    'scope_type' => ResponsibilityAssignment::SCOPE_SECTION,
                    'scope_id' => $new->id,
                    'role_code' => $a->role_code,
                    'user_id' => $a->user_id,
                    'effective_from' => now()->toDateString(),
                    'remarks' => "Inherited on split from {$section->code}",
                    'is_test' => $new->is_test,
                ]));

            $this->geometry->cutSection($road, $section);
            $this->geometry->cutSection($road, $new);

            $this->audit->log('road_section.split', $section,
                ['end_chainage_m' => $originalEnd],
                ['end_chainage_m' => $atChainageM, 'new_section_id' => $new->id, 'new_section_code' => $newCode],
            );

            return $new;
        });
    }

    private function assertValidRange(Road $road, int $start, int $end, ?int $ignoreId = null): void
    {
        if ($end <= $start) {
            throw ValidationException::withMessages(['end_chainage_m' => 'End chainage must be greater than start chainage.']);
        }
        if ($start < $road->start_chainage_m || $end > $road->end_chainage_m) {
            throw ValidationException::withMessages(['start_chainage_m' => sprintf(
                'Section must lie within the road chainage (%s – %s km).',
                number_format($road->start_chainage_m / 1000, 3), number_format($road->end_chainage_m / 1000, 3),
            )]);
        }

        $overlap = RoadSection::where('road_id', $road->id)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('start_chainage_m', '<', $end)
            ->where('end_chainage_m', '>', $start)
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages(['start_chainage_m' => "Overlaps section {$overlap->code} ("
                .number_format($overlap->start_chainage_m / 1000, 3).' – '.number_format($overlap->end_chainage_m / 1000, 3).' km).']);
        }
    }
}
