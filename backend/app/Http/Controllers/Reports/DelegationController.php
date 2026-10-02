<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Delegation\DelegationService;
use App\Enums\RoleCode;
use App\Http\Controllers\Controller;
use App\Models\Delegation;
use App\Models\User;
use App\Models\WorkflowAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DelegationController extends Controller
{
    public function __construct(private readonly DelegationService $delegations) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate(['status' => ['nullable', Rule::in(['scheduled', 'active', 'ended', 'cancelled'])]]);

        $delegations = Delegation::query()
            ->with(['primaryUser:id,name,employee_code', 'delegateUser:id,name,employee_code'])
            ->withCount(['assignments as transferred_count'])
            ->when(! $user->can('delegation.manage'), fn ($q) => $q->where(fn ($w) => $w
                ->where('primary_user_id', $user->id)->orWhere('delegate_user_id', $user->id)))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByRaw("FIELD(status, 'active', 'scheduled', 'ended', 'cancelled')")
            ->orderByDesc('starts_at')
            ->paginate(25)
            ->withQueryString();

        return view('delegations.index', compact('delegations', 'filters'));
    }

    public function create(Request $request): View
    {
        return view('delegations.create', [
            'officers' => $this->officers(),
            'primaryId' => $request->integer('primary_user_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'primary_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'delegate_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role_code' => ['required', Rule::in(['JE', 'AE', 'EE'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'transfer_mode' => ['required', Rule::in([Delegation::MODE_ALL_PENDING, Delegation::MODE_NEW_ONLY, Delegation::MODE_SELECTIVE])],
            'return_on_end' => ['nullable', 'boolean'],
        ]);
        $data['return_on_end'] = $request->boolean('return_on_end');

        $delegation = $this->delegations->create($request->user(), $data);

        return redirect()->route('delegations.show', $delegation)->with('success', $delegation->status === Delegation::STATUS_ACTIVE
            ? 'Delegation is active. New tasks now go to the stand-in.'
            : 'Delegation scheduled. It will start automatically.');
    }

    public function show(Request $request, Delegation $delegation): View
    {
        $user = $request->user();
        abort_unless($user->can('delegation.manage') || in_array($user->id, [$delegation->primary_user_id, $delegation->delegate_user_id], true), 403);

        $delegation->load(['primaryUser', 'delegateUser']);

        return view('delegations.show', [
            'delegation' => $delegation,
            'pending' => $delegation->status === Delegation::STATUS_ACTIVE ? $this->delegations->pendingTasks($delegation) : collect(),
            'transferred' => WorkflowAssignment::with(['user:id,name', 'step:id,name', 'instance.report' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'report_no')])
                ->where('delegation_id', $delegation->id)->orderBy('id')->get(),
            'history' => $delegation->auditLogs()->orderBy('id')->get(),
        ]);
    }

    public function transfer(Request $request, Delegation $delegation): RedirectResponse
    {
        $data = $request->validate(['assignments' => ['required', 'array', 'min:1'], 'assignments.*' => ['integer']], ['assignments.required' => 'Select at least one task.']);
        $count = $this->delegations->transfer($delegation, array_map('intval', $data['assignments']), $request->user());

        return back()->with('success', "{$count} task(s) transferred to {$delegation->delegateUser->name}.");
    }

    public function end(Request $request, Delegation $delegation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $cancel = $delegation->status === Delegation::STATUS_SCHEDULED;
        $this->delegations->end($delegation, $request->user(), $data['reason'], $cancel);

        return back()->with('success', $cancel ? 'Delegation cancelled.' : 'Delegation ended.'.($delegation->return_on_end ? ' Open delegated tasks were returned.' : ''));
    }

    /** @return Collection<int, User> */
    private function officers()
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', fn ($q) => $q->whereIn('code', array_map(fn ($r) => $r->value, RoleCode::engineerRoles())))
            ->with('roles:id,code')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'is_test']);
    }
}
