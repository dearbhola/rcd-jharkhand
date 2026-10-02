<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Task holder. Reassignment ends this row and creates a new one; rows are never deleted.
 */
class WorkflowAssignment extends Model
{
    use AppendOnly;

    public const VIA_PRIMARY = 'primary';

    public const VIA_DELEGATION = 'delegation';

    public const VIA_MANUAL = 'manual';

    public const VIA_ESCALATION = 'escalation';

    protected $guarded = ['id'];

    protected array $mutableAttributes = ['is_active', 'ended_at', 'end_reason'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function originalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_user_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(Delegation::class);
    }
}
