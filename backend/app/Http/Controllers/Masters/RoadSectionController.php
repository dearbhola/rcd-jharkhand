<?php

namespace App\Http\Controllers\Masters;

use App\Domain\Masters\ResponsibilityService;
use App\Domain\Masters\RoadSectionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\RoadSectionRequest;
use App\Models\ContractRoadSection;
use App\Models\Division;
use App\Models\ResponsibilityAssignment;
use App\Models\Road;
use App\Models\RoadSection;
use App\Models\SubDivision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoadSectionController extends Controller
{
    public function __construct(private readonly RoadSectionService $sections) {}

    public function store(RoadSectionRequest $request, Road $road): RedirectResponse
    {
        $section = $this->sections->create($road, $request->payload());

        return redirect()->route('roads.show', $road)->with('success', "Section {$section->code} added.");
    }

    public function show(RoadSection $roadSection, ResponsibilityService $responsibility): View
    {
        $roadSection->load(['road', 'division', 'subDivision']);

        return view('masters.roads.section', [
            'section' => $roadSection,
            'people' => $responsibility->forSection($roadSection),
            'history' => ResponsibilityAssignment::forScope(ResponsibilityAssignment::SCOPE_SECTION, $roadSection->id)
                ->with(['user:id,name,employee_code', 'creator:id,name'])->orderBy('role_code')->orderByDesc('effective_from')->get(),
            'contracts' => ContractRoadSection::with('contract.contractor:id,name')
                ->where('road_id', $roadSection->road_id)
                ->where('start_chainage_m', '<', $roadSection->end_chainage_m)
                ->where('end_chainage_m', '>', $roadSection->start_chainage_m)
                ->orderByDesc('effective_from')->get(),
        ]);
    }

    public function edit(RoadSection $roadSection): View
    {
        return view('masters.roads.section-form', [
            'section' => $roadSection->load('road'),
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
            'subDivisions' => SubDivision::orderBy('code')->get(['id', 'name', 'division_id']),
        ]);
    }

    public function update(RoadSectionRequest $request, RoadSection $roadSection): RedirectResponse
    {
        $this->sections->update($roadSection, $request->payload());

        return redirect()->route('roads.show', $roadSection->road_id)->with('success', "Section {$roadSection->code} updated.");
    }

    public function split(Request $request, RoadSection $roadSection): RedirectResponse
    {
        $request->merge(['split_chainage_m' => is_numeric($request->split_km) ? (int) round($request->split_km * 1000) : null, 'new_code' => strtoupper((string) $request->new_code)]);
        $data = $request->validate([
            'split_chainage_m' => ['required', 'integer'],
            'new_code' => ['required', 'string', 'max:40', 'alpha_dash'],
            'new_name' => ['nullable', 'string', 'max:255'],
        ], [], ['split_chainage_m' => 'split km']);

        $new = $this->sections->split($roadSection, $data['split_chainage_m'], $data['new_code'], $data['new_name'] ?? null);

        return redirect()->route('roads.show', $roadSection->road_id)->with('success', "Section {$roadSection->code} split; {$new->code} created and inherits the JE/AE/EE mapping.");
    }
}
