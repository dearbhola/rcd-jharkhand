<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Escalation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fired_at' => 'datetime'];
    }

    public function slaInstance(): BelongsTo
    {
        return $this->belongsTo(SlaInstance::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EscalationRule::class, 'escalation_rule_id');
    }

    public function notifiedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notified_user_id');
    }
}
