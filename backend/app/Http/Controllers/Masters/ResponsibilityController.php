<?php

namespace App\Http\Controllers\Masters;

use App\Domain\Masters\ResponsibilityService;
use App\Enums\RoleCode;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\ResponsibilityAssignment;
use App\Models\Road;
use App\Models\RoadSection;
use App\Models\SubDivision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResponsibilityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'division_id' => ['nullable', 'integer'],
            'sub_division_id' => ['nullable', 'integer'],
            'road_id' => ['nullable', 'integer'],
            'officer' => ['nullable', 'string', 'max:100'],
            'unmapped' => ['nullable', 'boolean'],
        ]);

        $sections = RoadSection::query()
            ->with('road:id,code,name')
            ->when($filters['division_id'] ?? null, fn ($q, $v) => $q->where('division_id', $v))
            ->when($filters['sub_division_id'] ?? null, fn ($q, $v) => $q->where('sub_division_id', $v))
            ->when($filters['road_id'] ?? null, fn ($q, $v) => $q->where('road_id', $v))
            ->when($filters['officer'] ?? null, fn ($q, $name) => $q->whereIn('id', ResponsibilityAssignment::query()
                ->where('scope_type', ResponsibilityAssignment::SCOPE_SECTION)->effectiveOn(now())
                ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$name}%")->orWhere('employee_code', $name))
                ->select('scope_id')))
            ->when($filters['unmapped'] ?? false, fn ($q) => $q->whereNotIn('id', ResponsibilityAssignment::query()
                ->where('scope_type', ResponsibilityAssignment::SCOPE_SECTION)->effectiveOn(now())
                ->select('scope_id')->groupBy('scope_id')->havingRaw('COUNT(DISTINCT role_code) = 3')))
            ->orderBy('road_id')->orderBy('start_chainage_m')
            ->paginate(50)
            ->withQueryString();

        $current = ResponsibilityAssignment::query()
            ->with('user:id,name,employee_code')
            ->where('scope_type', ResponsibilityAssignment::SCOPE_SECTION)
            ->whereIn('scope_id', $sections->pluck('id'))
            ->effectiveOn(now())
            ->get()
            ->groupBy('scope_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($r) => $r->role_code->value));

        return view('masters.responsibility.index', [
            'sections' => $sections,
            'current' => $current,
            'filters' => $filters,
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
            'subDivisions' => SubDivision::orderBy('code')->get(['id', 'name', 'division_id']),
            'roads' => Road::orderBy('code')->pluck('code', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('masters.responsibility.assign', [
            'roads' => Road::orderBy('code')->get(['id', 'code', 'name'])->mapWithKeys(fn ($r) => [$r->id => "{$r->code} — {$r->name}"]),
            'officers' => User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->whereHas('roles', fn ($q) => $q->whereIn('code', array_map(fn ($r) => $r->value, RoleCode::engineerRoles())))
                ->with('roles:id,code')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_code', 'designation', 'is_test']),
            'roadId' => $request->integer('road_id') ?: null,
            'preselected' => array_map('intval', (array) $request->query('sections', [])),
        ]);
    }

    public function store(Request $request, ResponsibilityService $responsibility): RedirectResponse
    {
        $data = $request->validate([
            'road_id' => ['required', Rule::exists('roads', 'id')],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['integer', Rule::exists('road_sections', 'id')->where('road_id', $request->input('road_id'))],
            'role_code' => ['required', Rule::in(array_map(fn ($r) => $r->value, RoleCode::engineerRoles()))],
            'user_id' => ['required', Rule::exists('users', 'id')],
            'effective_from' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], ['sections.required' => 'Select at least one section.']);

        $changed = $responsibility->assign(
            $request->user(),
            ResponsibilityAssignment::SCOPE_SECTION,
            array_map('intval', $data['sections']),
            RoleCode::from($data['role_code']),
            User::findOrFail($data['user_id']),
            $data['effective_from'],
            $data['remarks'] ?? null,
        );

        return redirect()->route('roads.show', $data['road_id'])
            ->with('success', $changed ? "{$data['role_code']} assigned on {$changed} section(s). Previous assignments were closed and kept as history." : 'No change — the officer was already assigned.');
    }
}
