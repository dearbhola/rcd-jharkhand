<?php

namespace App\Http\Controllers\Masters;

use App\Domain\Masters\RoadOverview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\RoadRequest;
use App\Models\Asset;
use App\Models\ContractRoadSection;
use App\Models\District;
use App\Models\Division;
use App\Models\Road;
use App\Models\RoadCategory;
use App\Models\SubDivision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoadController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'division_id' => ['nullable', 'integer'],
            'sub_division_id' => ['nullable', 'integer'],
            'road_category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'under_construction'])],
        ]);

        $roads = Road::query()
            ->with(['category:id,code', 'division:id,code,name', 'subDivision:id,code'])
            ->withCount('sections')
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('code', 'like', "%{$t}%")
                ->orWhere('name', 'like', "%{$t}%")->orWhere('road_number', 'like', "%{$t}%")))
            ->when($filters['division_id'] ?? null, fn ($q, $v) => $q->where('division_id', $v))
            ->when($filters['sub_division_id'] ?? null, fn ($q, $v) => $q->where('sub_division_id', $v))
            ->when($filters['road_category_id'] ?? null, fn ($q, $v) => $q->where('road_category_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('masters.roads.index', ['roads' => $roads, 'filters' => $filters, ...$this->lookups()]);
    }

    public function create(): View
    {
        return view('masters.roads.form', ['road' => new Road(['status' => 'active', 'start_chainage_m' => 0]), ...$this->lookups()]);
    }

    public function store(RoadRequest $request): RedirectResponse
    {
        $road = Road::create($request->payload());

        return redirect()->route('roads.show', $road)->with('success', 'Road created. Add sections and draw its geometry next.');
    }

    public function show(Request $request, Road $road, RoadOverview $overview): View
    {
        $road->load(['category', 'division', 'subDivision', 'district', 'currentGeometry', 'creator:id,name', 'updater:id,name']);
        $at = now();

        return view('masters.roads.show', [
            'road' => $road,
            'sections' => $overview->sections($road, $at),
            'assets' => Asset::with('type:id,name')->where('road_id', $road->id)->orderBy('chainage_m')->get(),
            'contractHistory' => ContractRoadSection::with(['contract.contractor:id,name', 'section:id,code'])
                ->where('road_id', $road->id)->orderByDesc('effective_from')->get(),
            'geometryVersions' => $road->geometries()->with('creator:id,name')->limit(10)->get(['id', 'road_id', 'version', 'length_m', 'source', 'is_current', 'remarks', 'created_by', 'created_at']),
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
            'subDivisions' => SubDivision::orderBy('code')->get(['id', 'name', 'division_id']),
        ]);
    }

    public function edit(Road $road): View
    {
        return view('masters.roads.form', ['road' => $road, ...$this->lookups()]);
    }

    public function update(RoadRequest $request, Road $road): RedirectResponse
    {
        $data = $request->payload();
        if ($road->currentGeometry()->exists()) {
            unset($data['length_m']); // measured length comes from the geometry
        }
        $road->update($data);

        return redirect()->route('roads.show', $road)->with('success', 'Road updated.');
    }

    /** @return array<string, mixed> */
    private function lookups(): array
    {
        return [
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
            'subDivisions' => SubDivision::orderBy('code')->get(['id', 'name', 'division_id']),
            'districts' => District::orderBy('name')->pluck('name', 'id'),
            'categories' => RoadCategory::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'),
        ];
    }
}
