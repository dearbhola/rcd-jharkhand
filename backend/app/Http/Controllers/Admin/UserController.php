<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\PasswordPolicy;
use App\Domain\Users\UserAdminService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\Role;
use App\Models\User;
use App\Support\TestDataMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserAdminService $users) {}

    public function index(Request $request, TestDataMode $testData): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::in(['active', 'suspended', 'inactive'])],
        ]);

        $users = User::query()
            ->with(['roles:id,code,name', 'contractor:id,name'])
            ->when(! $testData->includesTestData(), fn ($q) => $q->where('is_test', false))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")->orWhere('employee_code', 'like', "%{$term}%")))
            ->when($filters['role'] ?? null, fn ($q, $code) => $q->whereHas('roles', fn ($r) => $r->where('code', $code)))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::orderBy('name')->get(), 'filters' => $filters]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, ...$this->formOptions()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->user(), $request->attributesForSave(), $request->roleIds());

        return redirect()->route('admin.users.show', $user)->with('success', 'User created. They must change the password at first sign-in.');
    }

    public function show(User $user): View
    {
        $user->load(['roles', 'contractor']);
        $history = AuditLog::where('auditable_type', 'user')->where('auditable_id', $user->id)
            ->latest('id')->limit(20)->get();

        return view('admin.users.show', ['user' => $user, 'history' => $history]);
    }

    public function edit(User $user): View
    {
        $this->users->assertCanManage(request()->user(), $user);

        return view('admin.users.form', ['user' => $user->load('roles'), ...$this->formOptions()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($request->user(), $user, $request->attributesForSave(), $request->roleIds());

        return redirect()->route('admin.users.show', $user)->with('success', 'User updated.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->users->setStatus($request->user(), $user, $data['status'], $data['reason']);

        return back()->with('success', "Account status set to {$data['status']}.");
    }

    public function resetPassword(Request $request, User $user, PasswordPolicy $passwords): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', $passwords->rule()],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->users->resetPassword($request->user(), $user, $data['password'], $data['reason']);

        return back()->with('success', 'Temporary password set. The user must change it at next sign-in.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        $actor = request()->user();

        return [
            'roles' => Role::orderBy('name')->get()->reject(fn ($r) => $r->code === 'SUPER_ADMIN' && ! $actor->isSuperAdmin()),
            'contractors' => Contractor::orderBy('name')->get(['id', 'name', 'is_test']),
        ];
    }
}
