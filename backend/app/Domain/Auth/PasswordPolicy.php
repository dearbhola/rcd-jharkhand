<?php

namespace App\Domain\Auth;

use App\Enums\RoleCode;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Validation\Rules\Password;

/**
 * Strong-password rule and expiry, driven by system settings.
 */
class PasswordPolicy
{
    public function __construct(private readonly Settings $settings) {}

    public function rule(): Password
    {
        return Password::min($this->settings->int('auth.password_min_length'))
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();
    }

    /**
     * Staff must change a password set by someone else (null password_changed_at)
     * or older than the expiry window. Citizens are exempt from expiry.
     */
    public function mustChange(User $user): bool
    {
        if ($user->roleCodes()->every(fn ($c) => $c === RoleCode::CITIZEN->value)) {
            return false;
        }

        if ($user->password_changed_at === null) {
            return true;
        }

        $days = $this->settings->int('auth.password_expiry_days');

        return $days > 0 && $user->password_changed_at->lt(now()->subDays($days));
    }
}
