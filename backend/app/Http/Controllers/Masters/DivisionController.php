<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\DivisionRequest;
use App\Http\Requests\Masters\SubDivisionRequest;
use App\Models\District;
use App\Models\Division;
use App\Models\SubDivision;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DivisionController extends Controller
{
    public function index(): View
    {
        $divisions = Division::with(['district', 'subDivisions' => fn ($q) => $q->withCount('roadSections')->orderBy('code')])
            ->withCount('roads')
            ->orderBy('code')
            ->get();

        return view('masters.divisions.index', compact('divisions'));
    }

    public function create(): View
    {
        return view('masters.divisions.form', ['division' => new Division(['is_active' => true]), 'districts' => $this->districts()]);
    }

    public function store(DivisionRequest $request): RedirectResponse
    {
        Division::create($request->payload());

        return redirect()->route('divisions.index')->with('success', 'Division created.');
    }

    public function edit(Division $division): View
    {
        return view('masters.divisions.form', ['division' => $division, 'districts' => $this->districts()]);
    }

    public function update(DivisionRequest $request, Division $division): RedirectResponse
    {
        $division->update($request->payload());

        return redirect()->route('divisions.index')->with('success', 'Division updated.');
    }

    public function createSub(Division $division): View
    {
        return view('masters.divisions.sub-form', [
            'subDivision' => new SubDivision(['division_id' => $division->id, 'is_active' => true]),
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
            'districts' => $this->districts(),
        ]);
    }

    public function storeSub(SubDivisionRequest $request): RedirectResponse
    {
        $data = $request->payload();
        $data['is_test'] ??= Division::find($data['division_id'])?->is_test ?? false;
        SubDivision::create($data);

        return redirect()->route('divisions.index')->with('success', 'Sub-division created.');
    }

    public function editSub(SubDivision $subDivision): View
    {
        return view('masters.divisions.sub-form', [
            'subDivision' => $subDivision,
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
            'districts' => $this->districts(),
        ]);
    }

    public function updateSub(SubDivisionRequest $request, SubDivision $subDivision): RedirectResponse
    {
        $subDivision->update($request->payload());

        return redirect()->route('divisions.index')->with('success', 'Sub-division updated.');
    }

    private function districts()
    {
        return District::orderBy('name')->pluck('name', 'id');
    }
}
