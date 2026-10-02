<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\CitizenRegistrationService;
use App\Domain\Auth\Otp\OtpService;
use App\Domain\Auth\PasswordPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MobileRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Citizen self-registration: mobile number + OTP (D5). Staff accounts are created by administrators.
 */
class RegisterController extends Controller
{
    private const SESSION_KEY = 'pending_registration';

    public function __construct(
        private readonly OtpService $otp,
        private readonly CitizenRegistrationService $registration,
        private readonly PasswordPolicy $passwords,
    ) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'mobile' => [...MobileRule::rules(), 'unique:users,mobile'],
        ], ['mobile.unique' => 'This mobile number is already registered. Please sign in.']);

        $this->otp->send($data['mobile'], OtpService::PURPOSE_REGISTER, $request->ip());
        $request->session()->put(self::SESSION_KEY, $data);

        return redirect()->route('register.verify')->with('info', 'An OTP has been sent to '.$this->mask($data['mobile']).'.');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return $pending
            ? view('auth.register-verify', ['mobile' => $this->mask($pending['mobile'])])
            : redirect()->route('register');
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $pending) {
            return redirect()->route('register');
        }

        $data = $request->validate([
            'otp' => ['required', 'digits_between:4,8'],
            'password' => ['required', 'confirmed', $this->passwords->rule()],
        ]);

        $this->otp->verify($pending['mobile'], OtpService::PURPOSE_REGISTER, $data['otp']);
        $user = $this->registration->register($pending['name'], $pending['mobile'], $data['password']);

        $request->session()->forget(self::SESSION_KEY);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome! Your account has been created.');
    }

    private function mask(string $mobile): string
    {
        return str_repeat('•', 6).substr($mobile, -4);
    }
}
