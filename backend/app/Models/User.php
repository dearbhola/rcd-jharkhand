<?php

namespace App\Models;

use App\Enums\RoleCode;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'name', 'email', 'mobile', 'employee_code', 'designation', 'contractor_id',
        'password', 'status', 'is_test', 'email_verified_at', 'mobile_verified_at', 'password_changed_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected array $auditExclude = ['last_login_at'];

    /** @var Collection<int, string>|null per-instance permission cache */
    private ?Collection $permissionKeys = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_test' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot('assigned_at', 'assigned_by');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function responsibilityAssignments(): HasMany
    {
        return $this->hasMany(ResponsibilityAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(WorkflowAssignment::class)->where('is_active', true);
    }

    public function hasRole(RoleCode|string ...$codes): bool
    {
        $wanted = array_map(fn ($c) => $c instanceof RoleCode ? $c->value : $c, $codes);

        return $this->roleCodes()->intersect($wanted)->isNotEmpty();
    }

    /** @return Collection<int, string> */
    public function roleCodes(): Collection
    {
        return $this->loadMissing('roles')->roles->pluck('code');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleCode::SUPER_ADMIN);
    }

    /** Highest-precedence role, used for audit and default dashboard. */
    public function primaryRoleCode(): ?RoleCode
    {
        $held = $this->roleCodes()->all();

        foreach (RoleCode::cases() as $case) {
            if (in_array($case->value, $held, true)) {
                return $case;
            }
        }

        return null;
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissionKeys()->contains($key);
    }

    /** @return Collection<int, string> */
    public function permissionKeys(): Collection
    {
        return $this->permissionKeys ??= Permission::query()
            ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->where('user_roles.user_id', $this->id)
            ->distinct()
            ->pluck('permissions.key');
    }

    public function flushPermissionCache(): void
    {
        $this->permissionKeys = null;
        $this->unsetRelation('roles');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
