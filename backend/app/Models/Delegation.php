<?php

namespace App\Models;

use App\Enums\RoleCode;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTestFlag;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Temporary task routing while a JE/AE/EE is unavailable.
 * Does not touch the permanent responsibility mapping.
 */
class Delegation extends Model
{
    use Auditable, HasTestFlag;

    public const MODE_ALL_PENDING = 'all_pending';

    public const MODE_NEW_ONLY = 'new_only';

    public const MODE_SELECTIVE = 'selective';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'role_code' => RoleCode::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
            'ended_at' => 'datetime',
            'return_on_end' => 'boolean',
        ];
    }

    public function primaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_user_id');
    }

    public function delegateUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkflowAssignment::class);
    }

    public function scopeInForceAt(Builder $query, CarbonInterface $at): void
    {
        $query->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_ACTIVE])
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at);
    }
}
