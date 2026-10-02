<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasTestFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Inspection extends Model
{
    use AppendOnly, HasTestFlag;

    public const STAGE_VALIDATION = 'VALIDATION';

    public const STAGE_JE_REVIEW = 'JE_REVIEW';

    public const STAGE_AE_REVIEW = 'AE_REVIEW';

    public const STAGE_EE_APPROVAL = 'EE_APPROVAL';

    protected $guarded = ['id'];

    protected array $mutableAttributes = ['workflow_action_id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'gps_accuracy_m' => 'float',
            'distance_from_site_m' => 'float',
            'location_verified' => 'boolean',
            'location_override' => 'boolean',
            'inspected_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function repairAttempt(): BelongsTo
    {
        return $this->belongsTo(RepairAttempt::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function evidences(): MorphMany
    {
        return $this->morphMany(Evidence::class, 'evidenceable');
    }
}
