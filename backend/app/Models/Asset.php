<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTestFlag;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generic asset (bridge, culvert, guard wall, ...). JE/AE/EE and contractor are
 * inherited from the road section unless an asset-scoped responsibility exists.
 */
class Asset extends Model
{
    use Auditable, HasFactory, HasTestFlag, SoftDeletes, TracksAuthor;

    protected $guarded = ['id'];

    protected array $auditExclude = ['geojson'];

    protected function casts(): array
    {
        return [
            'chainage_m' => 'integer',
            'end_chainage_m' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'attributes' => 'array',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function road(): BelongsTo
    {
        return $this->belongsTo(Road::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(RoadSection::class, 'road_section_id');
    }

    public function responsibilityAssignments(): HasMany
    {
        return $this->hasMany(ResponsibilityAssignment::class, 'scope_id')
            ->where('scope_type', ResponsibilityAssignment::SCOPE_ASSET);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
