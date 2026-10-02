<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who was responsible for a report when it was routed. Superseded rows are kept
 * (is_current = false) so historical reports retain historical responsibility.
 */
class ReportResponsibility extends Model
{
    public const ROUTE_CONTRACTOR = 'contractor';

    public const ROUTE_DEPARTMENT = 'department';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'maintenance_active' => 'boolean',
            'is_current' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function je(): BelongsTo
    {
        return $this->belongsTo(User::class, 'je_user_id');
    }

    public function ae(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ae_user_id');
    }

    public function ee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ee_user_id');
    }
}
