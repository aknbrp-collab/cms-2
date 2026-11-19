<?php

namespace App\Jobs;

use App\Services\DataPurge;
use App\Support\Audit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Permanently delete old data chosen on the Data page, after a full backup.
 */
class PurgeData implements ShouldQueue
{
    use Queueable;

    public const STATUS_KEY = 'cms:purge:status';

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public string $kind, public string $before, public ?string $actor = null) {}

    public function handle(DataPurge $purge): void
    {
        $status = ['kind' => $this->kind, 'before' => $this->before, 'by' => $this->actor, 'started_at' => now()->toIso8601String()];
        self::setStatus([...$status, 'state' => 'backing_up']);

        if (Artisan::call('backup:run', ['--disable-notifications' => true]) !== 0) {
            self::setStatus([...$status, 'state' => 'failed', 'finished_at' => now()->toIso8601String(),
                'message' => __('The backup failed, so nothing was deleted: :output', ['output' => Str::limit(trim(Artisan::output()), 300)])]);

            return;
        }

        self::setStatus([...$status, 'state' => 'purging']);
        $counts = $purge->purge($this->kind, Carbon::parse($this->before)->startOfDay());

        Audit::record('data.purged', meta: ['kind' => $this->kind, 'before' => $this->before, 'by' => $this->actor, 'counts' => $counts]);
        self::setStatus([...$status, 'state' => 'done', 'counts' => $counts, 'finished_at' => now()->toIso8601String()]);
    }

    public function failed(?Throwable $exception): void
    {
        $status = self::status() ?? [];
        self::setStatus([...$status, 'kind' => $this->kind, 'before' => $this->before, 'state' => 'failed', 'finished_at' => now()->toIso8601String(),
            'message' => Str::limit((string) $exception?->getMessage(), 500)]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function status(): ?array
    {
        $status = Cache::get(self::STATUS_KEY);

        return is_array($status) ? $status : null;
    }

    /**
     * Whether a purge is running (three silent hours count as stuck).
     */
    public static function running(): bool
    {
        $status = self::status();

        return $status !== null
            && in_array($status['state'] ?? null, ['queued', 'backing_up', 'purging'], true)
            && Carbon::parse((string) ($status['started_at'] ?? 'now'))->gt(now()->subHours(3));
    }

    /**
     * @param  array<string, mixed>  $status
     */
    public static function setStatus(array $status): void
    {
        Cache::put(self::STATUS_KEY, $status, now()->addDays(7));
    }
}
