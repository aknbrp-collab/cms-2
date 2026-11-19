<?php

namespace App\Jobs;

use App\Services\Backups;
use App\Services\Restore\BackupRestorer;
use App\Services\Restore\RestoreStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Restore a backup chosen on the admin Data page, in the background.
 */
class RestoreBackup implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(
        public string $disk,
        public string $path,
        public ?string $maintenanceSecret = null,
        public ?string $actor = null,
    ) {}

    public function handle(Backups $backups, BackupRestorer $restorer, RestoreStatus $status): void
    {
        $backup = $backups->find($this->disk, $this->path);

        if ($backup === null) {
            $status->update(['state' => 'failed', 'message' => __('The backup no longer exists.'), 'finished_at' => now()->toIso8601String()]);

            return;
        }

        // Work on a local copy: the backup may live on remote storage.
        $local = storage_path('app/restore-temp/'.Str::uuid().'.zip');
        File::ensureDirectoryExists(dirname($local));
        $input = $backup->stream();
        $output = fopen($local, 'wb');

        if ($output === false) {
            $status->update(['state' => 'failed', 'message' => __('The backup could not be copied for restoring.'), 'finished_at' => now()->toIso8601String()]);

            return;
        }

        stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);

        try {
            $restorer->run($local, basename($this->path), backupFirst: true, maintenanceSecret: $this->maintenanceSecret, actor: $this->actor);
        } finally {
            File::delete($local);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(RestoreStatus::class)->update(['state' => 'failed', 'message' => Str::limit((string) $exception?->getMessage(), 700), 'finished_at' => now()->toIso8601String()]);
    }
}
