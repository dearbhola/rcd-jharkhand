<?php

namespace App\Domain\Auth;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Credential check with lockout. Users sign in with email, mobile or employee code.
 */
class LoginService
{
    public function __construct(
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function findByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        return User::query()
            ->where(fn ($q) => $q->where('email', Str::lower($identifier))
                ->orWhere('mobile', $identifier)
                ->orWhere('employee_code', Str::upper($identifier)))
            ->first();
    }

    public function attempt(Request $request, string $identifier, string $password, bool $remember = false): User
    {
        $key = $this->throttleKey($request, $identifier);
        $maxAttempts = $this->settings->int('auth.max_login_attempts');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages(['identifier' => "Too many failed attempts. Try again in {$minutes} minute(s)."]);
        }

        $user = $this->findByIdentifier($identifier);

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, $this->settings->int('auth.lockout_minutes') * 60);
            $this->audit->log('auth.login_failed', $user, null, ['identifier' => Str::limit($identifier, 100)], actor: $user);

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $this->audit->log('auth.locked_out', $user, null, ['identifier' => Str::limit($identifier, 100)], actor: $user);
            }

            throw ValidationException::withMessages(['identifier' => 'These credentials do not match our records.']);
        }

        if (! $user->isActive()) {
            $this->audit->log('auth.login_blocked', $user, null, ['status' => $user->status], actor: $user);

            throw ValidationException::withMessages(['identifier' => 'Your account is not active. Please contact the administrator.']);
        }

        RateLimiter::clear($key);
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->log('auth.login', $user, actor: $user);

        return $user;
    }

    public function logout(Request $request): void
    {
        if ($user = Auth::user()) {
            $this->audit->log('auth.logout', $user, actor: $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function throttleKey(Request $request, string $identifier): string
    {
        return 'login:'.Str::lower(trim($identifier)).'|'.$request->ip();
    }
}
