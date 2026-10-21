<?php

namespace App\Domain\Audit;

/**
 * Catat otomatis created/updated/deleted beserta nilai sebelum/sesudah.
 * Model dapat mendefinisikan $auditExclude untuk atribut yang tidak perlu dicatat.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditLogger::class)->log(
                event: $model->auditEventName('created'),
                subject: $model,
                new: $model->auditableAttributes($model->getAttributes()),
            );
        });

        static::updated(function ($model) {
            $changes = $model->auditableAttributes($model->getChanges());
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);

            app(AuditLogger::class)->log(
                event: $model->auditEventName('updated'),
                subject: $model,
                old: $old,
                new: $changes,
            );
        });

        static::deleted(function ($model) {
            app(AuditLogger::class)->log(
                event: $model->auditEventName('deleted'),
                subject: $model,
                old: $model->auditableAttributes($model->getOriginal()),
            );
        });
    }

    public function auditEventName(string $action): string
    {
        return str(class_basename($this))->snake().'.'.$action;
    }

    public function auditableAttributes(array $attributes): array
    {
        $exclude = array_merge(property_exists($this, 'auditExclude') ? $this->auditExclude : [], ['created_at']);

        return array_diff_key($attributes, array_flip($exclude));
    }
}
