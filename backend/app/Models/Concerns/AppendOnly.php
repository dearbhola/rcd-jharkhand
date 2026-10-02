<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * History records: inserts only. $mutableAttributes lists the few columns that
 * may be filled in later (e.g. a decision); everything else is frozen.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function ($model) {
            $allowed = array_merge($model->mutableAttributes ?? [], ['updated_at']);
            $illegal = array_diff(array_keys($model->getDirty()), $allowed);

            if ($illegal !== []) {
                throw new LogicException(class_basename($model).' is append-only; cannot change: '.implode(', ', $illegal));
            }
        });

        static::deleting(function ($model) {
            throw new LogicException(class_basename($model).' records cannot be deleted.');
        });
    }
}
