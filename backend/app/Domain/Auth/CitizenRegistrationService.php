<?php

namespace App\Domain\Auth;

use App\Domain\Audit\AuditLogger;
use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CitizenRegistrationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Call only after the mobile number's OTP has been verified. */
    public function register(string $name, string $mobile, string $password): User
    {
        return DB::transaction(function () use ($name, $mobile, $password) {
            if (User::withTrashed()->where('mobile', $mobile)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['mobile' => 'This mobile number is already registered.']);
            }

            $user = User::create([
                'name' => $name,
                'mobile' => $mobile,
                'password' => $password,
                'password_changed_at' => now(),
                'mobile_verified_at' => now(),
                'status' => User::STATUS_ACTIVE,
            ]);
            $user->roles()->attach(Role::where('code', RoleCode::CITIZEN->value)->value('id'));

            $this->audit->log('user.registered', $user, null, ['mobile' => $mobile, 'role' => RoleCode::CITIZEN->value], actor: $user);

            return $user;
        });
    }
}
