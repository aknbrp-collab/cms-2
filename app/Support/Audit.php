<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Write entries to the audit log.
 */
class Audit
{
    /**
     * Record an event, optionally about a subject model.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>|null  $meta
     */
    public static function record(
        string $event,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?array $meta = null,
    ): AuditLog {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::query()->create([
            'user_id' => auth()->id(),
            'site_id' => self::siteIdFor($subject),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'meta' => $meta,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
        ]);
    }

    protected static function siteIdFor(?Model $subject): ?string
    {
        if ($subject !== null && $subject->getAttribute('site_id') !== null) {
            return $subject->getAttribute('site_id');
        }

        return app(CurrentSite::class)->id();
    }
}
