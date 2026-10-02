<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class EscalationRule extends Model
{
    use Auditable;

    public const TRIGGER_BEFORE_DUE = 'before_due';

    public const TRIGGER_AFTER_DUE = 'after_due';

    public const ACTION_REMIND = 'remind';

    public const ACTION_ESCALATE = 'escalate';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['offset_hours' => 'float', 'level' => 'integer', 'notify_assignee' => 'boolean', 'is_active' => 'boolean'];
    }
}
