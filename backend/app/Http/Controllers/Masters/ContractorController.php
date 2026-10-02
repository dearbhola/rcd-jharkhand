<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\ContractorRequest;
use App\Models\Contractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'suspended', 'blacklisted', 'inactive'])],
        ]);

        $contractors = Contractor::query()
            ->withCount(['contracts', 'contracts as maintenance_contracts_count' => fn ($q) => $q->maintenanceActiveOn(now())])
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")
                ->orWhere('code', 'like', "%{$t}%")->orWhere('registration_no', 'like', "%{$t}%")))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('masters.contractors.index', compact('contractors', 'filters'));
    }

    public function create(): View
    {
        return view('masters.contractors.form', ['contractor' => new Contractor(['status' => 'active'])]);
    }

    public function store(ContractorRequest $request): RedirectResponse
    {
        $contractor = Contractor::create($request->payload());

        return redirect()->route('contractors.show', $contractor)->with('success', 'Contractor created.');
    }

    public function show(Contractor $contractor): View
    {
        $contractor->load(['contracts' => fn ($q) => $q->orderByDesc('maintenance_end_date'), 'users.roles']);

        return view('masters.contractors.show', compact('contractor'));
    }

    public function edit(Contractor $contractor): View
    {
        return view('masters.contractors.form', compact('contractor'));
    }

    public function update(ContractorRequest $request, Contractor $contractor): RedirectResponse
    {
        $contractor->update($request->payload());

        return redirect()->route('contractors.show', $contractor)->with('success', 'Contractor updated.');
    }
}
