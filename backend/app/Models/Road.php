<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTestFlag;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Road extends Model
{
    use Auditable, HasFactory, HasTestFlag, SoftDeletes, TracksAuthor;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_chainage_m' => 'integer',
            'end_chainage_m' => 'integer',
            'length_m' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoadCategory::class, 'road_category_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function subDivision(): BelongsTo
    {
        return $this->belongsTo(SubDivision::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(RoadSection::class)->orderBy('start_chainage_m');
    }

    public function geometries(): HasMany
    {
        return $this->hasMany(RoadGeometry::class)->orderByDesc('version');
    }

    public function currentGeometry(): HasOne
    {
        return $this->hasOne(RoadGeometry::class)->where('is_current', true);
    }

    public function chainageMarkers(): HasMany
    {
        return $this->hasMany(RoadChainageMarker::class)->orderBy('chainage_m');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function contractMappings(): HasMany
    {
        return $this->hasMany(ContractRoadSection::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
