<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;

/**
 * Backups made by spatie/laravel-backup (config/backup.php), as seen from
 * the admin Data page: per storage disk, newest first.
 */
class Backups
{
    public const STATUS_KEY = 'cms:backup:status';

    /**
     * Name of the backup folder on each disk.
     */
    public function name(): string
    {
        return (string) config('backup.backup.name');
    }

    /**
     * @return list<string>
     */
    public function disks(): array
    {
        return array_values((array) config('backup.backup.destination.disks'));
    }

    /**
     * Every disk with its backups.
     *
     * @return list<array{disk: string, reachable: bool, error: string|null, used: float, backups: list<array{path: string, file: string, date: CarbonInterface, size: float}>}>
     */
    public function destinations(): array
    {
        $destinations = [];

        foreach ($this->disks() as $disk) {
            $destination = BackupDestination::create($disk, $this->name());
            $reachable = $destination->isReachable();
            $list = [];

            if ($reachable) {
                foreach ($destination->backups() as $backup) {
                    $list[] = [
                        'path' => $backup->path(),
                        'file' => basename($backup->path()),
                        'date' => $backup->date(),
                        'size' => $backup->sizeInBytes(),
                    ];
                }
            }

            $destinations[] = [
                'disk' => $disk,
                'reachable' => $reachable,
                'error' => $reachable ? null : $destination->connectionError()?->getMessage(),
                'used' => $reachable ? $destination->usedStorage() : 0.0,
                'backups' => $list,
            ];
        }

        return $destinations;
    }

    /**
     * A backup on a configured disk, or null. Only .zip files inside the
     * backup folder count, so a request cannot reach other files.
     */
    public function find(string $disk, string $path): ?Backup
    {
        if (! in_array($disk, $this->disks(), true)
            || ! str_starts_with($path, $this->name().'/')
            || ! str_ends_with($path, '.zip')
            || str_contains($path, '..')) {
            return null;
        }

        return BackupDestination::create($disk, $this->name())->backups()
            ->first(fn (Backup $backup) => $backup->path() === $path);
    }

    /**
     * Progress of the last backup started from the admin: state ("running",
     * "done" or "failed"), only_db, started_at, finished_at and message.
     *
     * @return array<string, mixed>|null
     */
    public function status(): ?array
    {
        $status = Cache::get(self::STATUS_KEY);

        return is_array($status) ? $status : null;
    }

    /**
     * Whether a backup started from the admin is still running (one that has
     * run for more than three hours is treated as stuck).
     */
    public function running(): bool
    {
        $status = $this->status();

        return $status !== null
            && $status['state'] === 'running'
            && Carbon::parse($status['started_at'])->gt(now()->subHours(3));
    }

    /**
     * @param  array<string, mixed>  $status
     */
    public function setStatus(array $status): void
    {
        Cache::put(self::STATUS_KEY, $status, now()->addDays(7));
    }
}
