<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportLink extends Model
{
    public const DUPLICATE_OF = 'duplicate_of';

    public const RELATED = 'related';

    protected $guarded = ['id'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function linkedReport(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'linked_report_id');
    }
}
