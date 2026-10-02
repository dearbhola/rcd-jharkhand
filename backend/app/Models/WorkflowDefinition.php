<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    use Auditable;

    public const CONTRACTOR_MAINTENANCE = 'CONTRACTOR_MAINTENANCE';

    public const DEPARTMENT = 'DEPARTMENT';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sort_order');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }

    /** Latest active version of a definition code. */
    public function scopeActiveCode(Builder $query, string $code): void
    {
        $query->where('code', $code)->where('is_active', true)->orderByDesc('version');
    }
}
