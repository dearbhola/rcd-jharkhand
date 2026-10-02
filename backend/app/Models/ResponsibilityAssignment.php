<?php

namespace App\Models;

use App\Enums\RoleCode;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTestFlag;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permanent JE/AE/EE mapping for a road section (or asset override), with history.
 * Delegation never modifies these rows.
 */
class ResponsibilityAssignment extends Model
{
    use Auditable, HasFactory, HasTestFlag;

    public const SCOPE_SECTION = 'road_section';

    public const SCOPE_ASSET = 'asset';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'role_code' => RoleCode::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function scopeEffectiveOn(Builder $query, CarbonInterface $date): void
    {
        $query->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    public function scopeForScope(Builder $query, string $scopeType, int $scopeId): void
    {
        $query->where('scope_type', $scopeType)->where('scope_id', $scopeId);
    }
}
