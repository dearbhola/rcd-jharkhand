<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasTestFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One repair submission. Only the review outcome fields may be filled in later.
 */
class RepairAttempt extends Model
{
    use AppendOnly, HasTestFlag;

    public const OUTCOME_PENDING = 'pending';

    public const OUTCOME_ACCEPTED_JE = 'accepted_je';

    public const OUTCOME_ACCEPTED_AE = 'accepted_ae';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_APPROVED = 'approved';

    protected $guarded = ['id'];

    protected array $mutableAttributes = ['outcome', 'rejected_stage', 'rejection_reason', 'rejected_by', 'decided_at', 'workflow_action_id'];

    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'gps_accuracy_m' => 'float',
            'captured_at' => 'datetime',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class)->orderBy('inspected_at');
    }

    public function evidences(): MorphMany
    {
        return $this->morphMany(Evidence::class, 'evidenceable');
    }
}
