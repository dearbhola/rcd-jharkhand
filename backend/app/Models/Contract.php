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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use Auditable, HasFactory, HasTestFlag, SoftDeletes, TracksAuthor;

    public const STATUS_ACTIVE = 'active';

    /** Statuses that never confer maintenance responsibility (completed contracts still do, for their dates). */
    public const NON_RESPONSIBLE_STATUSES = ['draft', 'terminated'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'agreement_date' => 'date',
            'work_order_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'maintenance_start_date' => 'date',
            'maintenance_end_date' => 'date',
            'contract_value' => 'decimal:2',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function roadSections(): HasMany
    {
        return $this->hasMany(ContractRoadSection::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class);
    }

    /**
     * Maintenance responsibility is decided ONLY by the maintenance dates,
     * never by the general contract end date.
     */
    public function isMaintenanceActiveOn(CarbonInterface $date): bool
    {
        if (in_array($this->status, self::NON_RESPONSIBLE_STATUSES, true) || ! $this->maintenance_start_date || ! $this->maintenance_end_date) {
            return false;
        }

        $day = $date->toDateString();

        return $day >= $this->maintenance_start_date->toDateString()
            && $day <= $this->maintenance_end_date->toDateString();
    }

    public function scopeMaintenanceActiveOn(Builder $query, CarbonInterface $date): void
    {
        $query->whereNotIn('status', self::NON_RESPONSIBLE_STATUSES)
            ->whereDate('maintenance_start_date', '<=', $date)
            ->whereDate('maintenance_end_date', '>=', $date);
    }
}
