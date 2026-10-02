<?php

namespace App\Models;

use App\Models\Concerns\HasTestFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SlaInstance extends Model
{
    use HasTestFlag;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hours' => 'float',
            'started_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'breached_at' => 'datetime',
        ];
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SlaRule::class, 'sla_rule_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    public function isBreached(?Carbon $at = null): bool
    {
        $end = $this->completed_at ?? $at ?? now();

        return $end->greaterThan($this->due_at);
    }

    /** Seconds remaining (negative when overdue). */
    public function remainingSeconds(?Carbon $at = null): int
    {
        return (int) ($at ?? now())->diffInSeconds($this->due_at, false);
    }
}
