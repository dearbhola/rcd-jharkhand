<?php

namespace App\Models\Concerns;

use App\Domain\Audit\AuditLogger;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Writes create/update/delete diffs to the audit trail.
 *
 * Business events (assign, reject, approve, ...) are logged explicitly by
 * domain services; this trait covers master-data changes.
 * Hidden attributes and $auditExclude are never written.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditLogger::class)->log($model->auditPrefix().'.created', $model, null, $model->auditableValues($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = $model->auditableValues($model->getChanges());
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->auditableValues($model->getOriginal()), $changes);
            app(AuditLogger::class)->log($model->auditPrefix().'.updated', $model, $old, $changes);
        });

        static::deleted(function ($model) {
            app(AuditLogger::class)->log($model->auditPrefix().'.deleted', $model, $model->auditableValues($model->getAttributes()));
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    protected function auditPrefix(): string
    {
        return $this->getMorphClass();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function auditableValues(array $values): array
    {
        $exclude = array_merge($this->getHidden(), $this->auditExclude ?? [], ['created_at', 'updated_at', 'created_by', 'updated_by']);

        return array_diff_key($values, array_flip($exclude));
    }
}
