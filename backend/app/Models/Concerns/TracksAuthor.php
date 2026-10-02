<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Fills created_by / updated_by from the authenticated user.
 * Only use on tables that have both columns.
 */
trait TracksAuthor
{
    public static function bootTracksAuthor(): void
    {
        static::creating(function ($model) {
            if (Auth::id() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::saving(function ($model) {
            if (Auth::id()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
