<?php

namespace App\Domain\Gis;

use App\Domain\Reporting\ReportStatus;
use App\Enums\RoleCode;
use App\Models\Asset;
use App\Models\ContractRoadSection;
use App\Models\GisFeature;
use App\Models\Report;
use App\Models\ResponsibilityAssignment;
use App\Models\RoadSection;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Viewport-limited map layers. Geometry is simplified to the zoom level so payloads
 * stay small; nothing outside the requested bounding box is sent.
 */
class MapLayerService
{
    private const MAX_FEATURES = 3000;

    public function __construct(private readonly GisEngine $gis) {}

    /**
     * @param  array{min_lat: float, min_lng: float, max_lat: float, max_lng: float}  $bbox
     * @param  array<string, mixed>  $filters  division_id, sub_division_id, contractor_id, je_id, ae_id, ee_id, maintenance (active|department)
     */
    public function sections(array $bbox, int $zoom, array $filters, CarbonInterface $at): array
    {
        $ids = $this->gis->featuresInBbox(GisFeature::TYPE_SECTION, $bbox['min_lat'], $bbox['min_lng'], $bbox['max_lat'], $bbox['max_lng'], self::MAX_FEATURES);

        $sections = RoadSection::query()
            ->with('road:id,code,name')
            ->whereIn('id', $ids)
            ->whereNotNull('geojson')
            ->when($filters['division_id'] ?? null, fn ($q, $v) => $q->where('division_id', $v))
            ->when($filters['sub_division_id'] ?? null, fn ($q, $v) => $q->where('sub_division_id', $v))
            ->get();

        $context = $this->context($sections, $at);
        $tolerance = $this->toleranceMetres($zoom, ($bbox['min_lat'] + $bbox['max_lat']) / 2);

        $features = [];
        foreach ($sections as $section) {
            $mapping = $context['mappings']->first(fn ($m) => $m->road_id === $section->road_id
                && $m->start_chainage_m < $section->end_chainage_m && $m->end_chainage_m > $section->start_chainage_m);
            $contract = $mapping?->contract;
            $maintenance = (bool) $contract?->isMaintenanceActiveOn($at);
            $people = $context['people'][$section->id] ?? [];

            if (! $this->passes($filters, $contract?->contractor_id, $maintenance, $people)) {
                continue;
            }

            $line = LineString::fromGeoJson($section->geojson)->simplify($tolerance);
            $features[] = [
                'type' => 'Feature',
                'id' => $section->id,
                'geometry' => $line->toGeoJson(6),
                'properties' => [
                    'section_id' => $section->id,
                    'road_id' => $section->road_id,
                    'road' => $section->road->code,
                    'section' => $section->code,
                    'km' => km($section->start_chainage_m).' – '.km($section->end_chainage_m),
                    'contractor' => $contract?->contractor?->name,
                    'maintenance' => $maintenance,
                    'status' => $section->status,
                    'is_test' => $section->is_test,
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features, 'truncated' => count($ids) >= self::MAX_FEATURES];
    }

    /** @param array{min_lat: float, min_lng: float, max_lat: float, max_lng: float} $bbox */
    public function assets(array $bbox, ?int $assetTypeId = null): array
    {
        $ids = $this->gis->featuresInBbox(GisFeature::TYPE_ASSET, $bbox['min_lat'], $bbox['min_lng'], $bbox['max_lat'], $bbox['max_lng'], self::MAX_FEATURES);

        $features = Asset::query()
            ->with(['type:id,name,code', 'road:id,code'])
            ->whereIn('id', $ids)
            ->when($assetTypeId, fn ($q, $v) => $q->where('asset_type_id', $v))
            ->get()
            ->map(fn (Asset $a) => [
                'type' => 'Feature',
                'id' => $a->id,
                'geometry' => ['type' => 'Point', 'coordinates' => [round($a->longitude, 6), round($a->latitude, 6)]],
                'properties' => [
                    'asset_id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type->name, 'type_code' => $a->type->code,
                    'road' => $a->road->code, 'km' => km($a->chainage_m), 'status' => $a->status, 'is_test' => $a->is_test,
                ],
            ])->all();

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * Damage reports visible to the user inside the viewport.
     *
     * @param  array{min_lat: float, min_lng: float, max_lat: float, max_lng: float}  $bbox
     * @param  array<string, mixed>  $filters  severity_id, status_group (open|repairing|review|closed), from, to, division_id
     */
    public function reports(array $bbox, array $filters, User $user): array
    {
        $groups = [
            'open' => [ReportStatus::PENDING_VALIDATION, ReportStatus::ASSIGNED, ReportStatus::DEPARTMENT_PENDING],
            'repairing' => [ReportStatus::IN_PROGRESS, ReportStatus::REOPENED],
            'review' => [ReportStatus::JE_REVIEW, ReportStatus::AE_REVIEW, ReportStatus::EE_APPROVAL],
            'closed' => ReportStatus::terminal(),
        ];

        $features = Report::visibleTo($user)
            ->with(['severity:id,name,color', 'category:id,name', 'road:id,code'])
            ->whereBetween('latitude', [$bbox['min_lat'], $bbox['max_lat']])
            ->whereBetween('longitude', [$bbox['min_lng'], $bbox['max_lng']])
            ->when($filters['severity_id'] ?? null, fn ($q, $v) => $q->where('severity_id', $v))
            ->when($filters['division_id'] ?? null, fn ($q, $v) => $q->where('division_id', $v))
            ->when(($filters['status_group'] ?? null) && isset($groups[$filters['status_group']]), fn ($q) => $q->whereIn('status', $groups[$filters['status_group']]))
            ->when(! ($filters['status_group'] ?? null), fn ($q) => $q->whereNotIn('status', ReportStatus::terminal()))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<', Carbon::parse($v)->addDay()))
            ->latest('id')
            ->limit(self::MAX_FEATURES)
            ->get()
            ->map(fn ($r) => [
                'type' => 'Feature',
                'id' => $r->id,
                'geometry' => ['type' => 'Point', 'coordinates' => [round($r->longitude, 6), round($r->latitude, 6)]],
                'properties' => [
                    'report_no' => $r->report_no, 'url' => route('reports.show', $r), 'status' => $r->status,
                    'status_label' => ReportStatus::labels()[$r->status][0] ?? $r->status,
                    'severity' => $r->severity->name, 'color' => $r->severity->color, 'category' => $r->category->name,
                    'road' => $r->road?->code, 'km' => km($r->chainage_m), 'date' => $r->created_at->format('d-M-Y'), 'is_test' => $r->is_test,
                ],
            ])->all();

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /** Douglas–Peucker tolerance ≈ one screen pixel at this zoom. */
    public function toleranceMetres(int $zoom, float $lat): float
    {
        return $zoom >= 16 ? 0.0 : 156543.03 * cos(deg2rad($lat)) / (2 ** $zoom);
    }

    /**
     * @param  Collection<int, RoadSection>  $sections
     * @return array{mappings: Collection, people: array<int, array<string, int>>}
     */
    private function context(Collection $sections, CarbonInterface $at): array
    {
        $mappings = ContractRoadSection::query()
            ->with('contract.contractor:id,name')
            ->whereIn('road_id', $sections->pluck('road_id')->unique())
            ->where('status', ContractRoadSection::STATUS_ACTIVE)
            ->whereDate('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $at))
            ->get();

        $people = [];
        ResponsibilityAssignment::query()
            ->where('scope_type', ResponsibilityAssignment::SCOPE_SECTION)
            ->whereIn('scope_id', $sections->pluck('id'))
            ->effectiveOn($at)
            ->get(['scope_id', 'role_code', 'user_id'])
            ->each(function ($row) use (&$people) {
                $people[$row->scope_id][$row->role_code->value] = $row->user_id;
            });

        return ['mappings' => $mappings, 'people' => $people];
    }

    /** @param array<string, int> $people */
    private function passes(array $filters, ?int $contractorId, bool $maintenance, array $people): bool
    {
        if (($filters['contractor_id'] ?? null) && (int) $filters['contractor_id'] !== $contractorId) {
            return false;
        }
        if (($filters['maintenance'] ?? null) === 'active' && ! $maintenance) {
            return false;
        }
        if (($filters['maintenance'] ?? null) === 'department' && $maintenance) {
            return false;
        }
        foreach (RoleCode::engineerRoles() as $role) {
            $key = strtolower($role->value).'_id';
            if (($filters[$key] ?? null) && (int) $filters[$key] !== ($people[$role->value] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
