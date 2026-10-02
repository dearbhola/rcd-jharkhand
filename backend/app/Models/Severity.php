<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Severity extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'rank' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('rank');
    }
}
