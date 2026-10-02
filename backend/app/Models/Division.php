<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTestFlag;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use Auditable, HasFactory, HasTestFlag, TracksAuthor;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function subDivisions(): HasMany
    {
        return $this->hasMany(SubDivision::class);
    }

    public function roads(): HasMany
    {
        return $this->hasMany(Road::class);
    }

    public function roadSections(): HasMany
    {
        return $this->hasMany(RoadSection::class);
    }
}
