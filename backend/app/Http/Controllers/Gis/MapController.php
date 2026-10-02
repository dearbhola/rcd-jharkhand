<?php

namespace App\Http\Controllers\Gis;

use App\Domain\Gis\MapLayerService;
use App\Domain\Masters\ResponsibilityService;
use App\Domain\Reporting\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\Contractor;
use App\Models\ContractRoadSection;
use App\Models\Division;
use App\Models\Report;
use App\Models\RoadSection;
use App\Models\Severity;
use App\Models\SubDivision;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Map dashboard (§34). Layers are fetched per viewport.
 */
class MapController extends Controller
{
    public function index(Settings $settings): View
    {
        $engineers = fn (string $role) => User::whereHas('roles', fn ($q) => $q->where('code', $role))->orderBy('name')->pluck('name', 'id');

        return view('gis.map', [
            'mapConfig' => GeometryController::mapConfig($settings),
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
            'subDivisions' => SubDivision::orderBy('code')->get(['id', 'name', 'division_id']),
            'contractors' => Contractor::orderBy('name')->pluck('name', 'id'),
            'assetTypes' => AssetType::where('is_active', true)->where('code', '!=', AssetType::ROAD)->orderBy('sort_order')->pluck('name', 'id'),
            'severities' => Severity::orderBy('rank')->pluck('name', 'id'),
            'jes' => $engineers('JE'), 'aes' => $engineers('AE'), 'ees' => $engineers('EE'),
        ]);
    }

    public function sections(Request $request, MapLayerService $layers): JsonResponse
    {
        $filters = $request->validate([
            'bbox' => ['required', 'string'],
            'zoom' => ['required', 'integer', 'between:1,22'],
            'division_id' => ['nullable', 'integer'],
            'sub_division_id' => ['nullable', 'integer'],
            'contractor_id' => ['nullable', 'integer'],
            'je_id' => ['nullable', 'integer'],
            'ae_id' => ['nullable', 'integer'],
            'ee_id' => ['nullable', 'integer'],
            'maintenance' => ['nullable', 'in:active,department'],
        ]);

        return response()->json($layers->sections($this->bbox($filters['bbox']), (int) $filters['zoom'], $filters, now()));
    }

    public function assets(Request $request, MapLayerService $layers): JsonResponse
    {
        $data = $request->validate(['bbox' => ['required', 'string'], 'asset_type_id' => ['nullable', 'integer']]);

        return response()->json($layers->assets($this->bbox($data['bbox']), $data['asset_type_id'] ?? null));
    }

    public function reports(Request $request, MapLayerService $layers): JsonResponse
    {
        $filters = $request->validate([
            'bbox' => ['required', 'string'],
            'severity_id' => ['nullable', 'integer'],
            'division_id' => ['nullable', 'integer'],
            'status_group' => ['nullable', 'in:open,repairing,review,closed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json($layers->reports($this->bbox($filters['bbox']), $filters, $request->user()));
    }

    /** Click on a road: road → section → contract → contractor → JE → AE → EE → issues → history. */
    public function sectionInfo(Request $request, RoadSection $roadSection, ResponsibilityService $responsibility): JsonResponse
    {
        $openIssues = Report::visibleTo($request->user())->where('road_section_id', $roadSection->id)
            ->whereNotIn('status', ReportStatus::terminal())->count();
        $roadSection->load('road');
        $at = now();
        $mapping = ContractRoadSection::query()
            ->with('contract.contractor:id,name,mobile')
            ->where('road_id', $roadSection->road_id)
            ->where('status', ContractRoadSection::STATUS_ACTIVE)
            ->where('start_chainage_m', '<', $roadSection->end_chainage_m)
            ->where('end_chainage_m', '>', $roadSection->start_chainage_m)
            ->whereDate('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $at))
            ->first();
        $people = $responsibility->forSection($roadSection, $at);
        $person = fn ($u) => $u ? ['name' => $u->name, 'mobile' => $u->mobile] : null;

        return response()->json([
            'road' => ['code' => $roadSection->road->code, 'name' => $roadSection->road->name, 'url' => route('roads.show', $roadSection->road_id)],
            'section' => ['code' => $roadSection->code, 'km' => km($roadSection->start_chainage_m).' – '.km($roadSection->end_chainage_m), 'url' => route('road-sections.show', $roadSection)],
            'contract' => $mapping ? [
                'no' => $mapping->contract->contract_no, 'url' => route('contracts.show', $mapping->contract_id),
                'maintenance' => d($mapping->contract->maintenance_start_date).' → '.d($mapping->contract->maintenance_end_date),
                'maintenance_active' => $mapping->contract->isMaintenanceActiveOn($at),
            ] : null,
            'contractor' => $mapping ? ['name' => $mapping->contract->contractor->name, 'mobile' => $mapping->contract->contractor->mobile] : null,
            'je' => $person($people['JE']), 'ae' => $person($people['AE']), 'ee' => $person($people['EE']),
            'open_issues' => $openIssues,
            'open_issues_url' => route('reports.index', ['q' => $roadSection->road->code]),
            'repair_history' => null, // Phase 6
        ]);
    }

    /** @return array{min_lat: float, min_lng: float, max_lat: float, max_lng: float} */
    private function bbox(string $bbox): array
    {
        $parts = array_map('floatval', explode(',', $bbox));
        if (count($parts) !== 4) {
            throw ValidationException::withMessages(['bbox' => 'bbox must be minLng,minLat,maxLng,maxLat.']);
        }
        [$minLng, $minLat, $maxLng, $maxLat] = $parts;
        if ($minLat > $maxLat || $minLng > $maxLng || $minLat < -90 || $maxLat > 90 || $minLng < -180 || $maxLng > 180) {
            throw ValidationException::withMessages(['bbox' => 'bbox is out of range.']);
        }

        return ['min_lat' => $minLat, 'min_lng' => $minLng, 'max_lat' => $maxLat, 'max_lng' => $maxLng];
    }
}
