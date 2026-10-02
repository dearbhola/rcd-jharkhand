<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadChainageMarker extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['chainage_m' => 'integer', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function road(): BelongsTo
    {
        return $this->belongsTo(Road::class);
    }
}
