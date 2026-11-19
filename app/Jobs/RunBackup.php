<?php

namespace App\Jobs;

use App\Services\Backups;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

/**
 * Run `backup:run` in the background for the admin Data page, recording
 * progress so the page can show it.
 */
class RunBackup implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public bool $onlyDb = false) {}

    public function handle(Backups $backups): void
    {
        $started = now()->toIso8601String();
        $backups->setStatus(['state' => 'running', 'only_db' => $this->onlyDb, 'started_at' => $started]);

        try {
            $exitCode = Artisan::call('backup:run', array_filter([
                '--only-db' => $this->onlyDb,
                '--disable-notifications' => true,
            ]));
            $output = trim(Artisan::output());
        } catch (Throwable $exception) {
            $exitCode = 1;
            $output = $exception->getMessage();
        }

        $backups->setStatus([
            'state' => $exitCode === 0 ? 'done' : 'failed',
            'only_db' => $this->onlyDb,
            'started_at' => $started,
            'finished_at' => now()->toIso8601String(),
            'message' => $exitCode === 0 ? null : Str::limit($output, 500),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $backups = app(Backups::class);
        $status = $backups->status() ?? ['only_db' => $this->onlyDb, 'started_at' => now()->toIso8601String()];

        $backups->setStatus([...$status, 'state' => 'failed', 'finished_at' => now()->toIso8601String(), 'message' => Str::limit((string) $exception?->getMessage(), 500)]);
    }
}
