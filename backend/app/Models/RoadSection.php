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

class RoadSection extends Model
{
    use Auditable, HasFactory, HasTestFlag, SoftDeletes, TracksAuthor;

    protected $guarded = ['id'];

    protected array $auditExclude = ['geojson'];

    protected function casts(): array
    {
        return [
            'start_chainage_m' => 'integer',
            'end_chainage_m' => 'integer',
            'length_m' => 'integer',
        ];
    }

    public function road(): BelongsTo
    {
        return $this->belongsTo(Road::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function subDivision(): BelongsTo
    {
        return $this->belongsTo(SubDivision::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function contractMappings(): HasMany
    {
        return $this->hasMany(ContractRoadSection::class);
    }

    public function responsibilityAssignments(): HasMany
    {
        return $this->hasMany(ResponsibilityAssignment::class, 'scope_id')
            ->where('scope_type', ResponsibilityAssignment::SCOPE_SECTION);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function containsChainage(int $chainageM): bool
    {
        return $chainageM >= $this->start_chainage_m && $chainageM <= $this->end_chainage_m;
    }
}
