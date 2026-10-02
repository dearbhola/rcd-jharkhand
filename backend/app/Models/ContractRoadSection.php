<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTestFlag;
use App\Models\Concerns\TracksAuthor;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contract's coverage of a chainage range on a road for a period.
 * Never edited to change history: end it (effective_to) and add a new row.
 */
class ContractRoadSection extends Model
{
    use Auditable, HasFactory, HasTestFlag, TracksAuthor;

    public const STATUS_ACTIVE = 'active';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'start_chainage_m' => 'integer',
            'end_chainage_m' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function road(): BelongsTo
    {
        return $this->belongsTo(Road::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(RoadSection::class, 'road_section_id');
    }

    /** Mappings in force on a date that cover a chainage on a road. */
    public function scopeCovering(Builder $query, int $roadId, int $chainageM, CarbonInterface $date): void
    {
        $query->where('road_id', $roadId)
            ->where('status', self::STATUS_ACTIVE)
            ->where('start_chainage_m', '<=', $chainageM)
            ->where('end_chainage_m', '>=', $chainageM)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }
}
