<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\PasswordPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function __construct(
        private readonly PasswordPolicy $passwords,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(Request $request): View
    {
        return view('auth.change-password', ['forced' => $this->passwords->mustChange($request->user())]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', $this->passwords->rule()],
        ]);

        if (Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => 'The new password must differ from the current one.']);
        }

        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->saveQuietly();
        $request->session()->regenerate();
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        $this->audit->log('user.password_changed', $user);

        return redirect()->route('dashboard')->with('success', 'Your password has been changed.');
    }
}
