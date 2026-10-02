<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Versioned road geometry. Immutable once written.
 */
class RoadGeometry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'length_m' => 'integer'];
    }

    protected static function booted(): void
    {
        // Only the is_current flag may change (when a newer version supersedes this one).
        static::updating(function (RoadGeometry $geometry) {
            if (array_diff(array_keys($geometry->getDirty()), ['is_current']) !== []) {
                throw new LogicException('Road geometry versions are immutable; create a new version.');
            }
        });
        static::deleting(fn () => throw new LogicException('Road geometry versions cannot be deleted.'));
    }

    public function road(): BelongsTo
    {
        return $this->belongsTo(Road::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, mixed> */
    public function geometry(): array
    {
        return json_decode($this->geojson, true);
    }
}
