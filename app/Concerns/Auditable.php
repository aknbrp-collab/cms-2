<?php

namespace App\Concerns;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Record create, update and delete events of a model in the audit log.
 *
 * Hidden attributes (passwords, tokens, secrets) are never written.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model) {
            Audit::record('created', $model, new: $model->auditValues($model->getAttributes()));
        });

        static::updated(function (self $model) {
            $changes = $model->auditValues($model->getChanges());
            unset($changes[$model->getUpdatedAtColumn()]);

            if ($changes === []) {
                return;
            }

            Audit::record(
                'updated',
                $model,
                old: array_intersect_key($model->auditValues($model->getOriginal()), $changes),
                new: $changes,
            );
        });

        static::deleted(function (self $model) {
            Audit::record('deleted', $model, old: $model->auditValues($model->getAttributes()));
        });
    }

    /**
     * Strip hidden and excluded attributes from the given values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditValues(array $values): array
    {
        return array_diff_key($values, array_flip([...$this->getHidden(), ...$this->auditExclude()]));
    }

    /**
     * Additional attributes that should never be written to the audit log.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return [];
    }
}
