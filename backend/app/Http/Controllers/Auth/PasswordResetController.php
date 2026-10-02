<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Otp\OtpService;
use App\Domain\Auth\PasswordPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MobileRule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Forgot password via mobile OTP (citizens have no email). Never reveals whether a number is registered.
 */
class PasswordResetController extends Controller
{
    private const SESSION_KEY = 'password_reset_mobile';

    public function __construct(
        private readonly OtpService $otp,
        private readonly PasswordPolicy $passwords,
        private readonly AuditLogger $audit,
    ) {}

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $data = $request->validate(['mobile' => MobileRule::rules()]);

        if (User::where('mobile', $data['mobile'])->where('status', User::STATUS_ACTIVE)->exists()) {
            $this->otp->send($data['mobile'], OtpService::PURPOSE_RESET_PASSWORD, $request->ip());
        }

        $request->session()->put(self::SESSION_KEY, $data['mobile']);

        return redirect()->route('password.reset')->with('info', 'If this number is registered, an OTP has been sent to it.');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        return $request->session()->has(self::SESSION_KEY) ? view('auth.reset-password') : redirect()->route('password.forgot');
    }

    public function update(Request $request): RedirectResponse
    {
        $mobile = $request->session()->get(self::SESSION_KEY);
        if (! $mobile) {
            return redirect()->route('password.forgot');
        }

        $data = $request->validate([
            'otp' => ['required', 'digits_between:4,8'],
            'password' => ['required', 'confirmed', $this->passwords->rule()],
        ]);

        $this->otp->verify($mobile, OtpService::PURPOSE_RESET_PASSWORD, $data['otp']);

        $user = User::where('mobile', $mobile)->firstOrFail();
        DB::transaction(function () use ($user, $data) {
            $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->saveQuietly();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            $this->audit->log('user.password_reset_self', $user, actor: $user, comment: 'Reset via OTP');
        });

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')->with('success', 'Your password has been reset. Please sign in.');
    }
}
