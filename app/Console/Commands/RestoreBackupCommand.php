<?php

namespace App\Console\Commands;

use App\Services\Backups;
use App\Services\Restore\BackupRestorer;
use App\Services\Restore\RestoreStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Restore a backup from the command line, e.g. when the admin panel itself
 * is unavailable. Same steps and safeguards as the Data page.
 */
#[Signature('cms:restore
    {archive? : Path to a backup .zip on this server}
    {--disk= : Restore from a backup disk instead (with --path)}
    {--path= : Backup path on that disk, e.g. cms-pondok/2026-11-20-02-00-00.zip}
    {--no-backup : Skip the backup of the current data taken first}
    {--force : Do not ask for confirmation}')]
#[Description('Restore the database and uploaded files from a backup')]
class RestoreBackupCommand extends Command
{
    public function handle(BackupRestorer $restorer, RestoreStatus $status, Backups $backups): int
    {
        foreach ($restorer->checks() as $check) {
            $this->line(($check['ok'] ? '<info>✔</info> ' : '<error>✘</error> ').$check['label'].': '.$check['message']);
        }

        if (! $restorer->ready()) {
            $this->error('The server is not ready for a restore.');

            return self::FAILURE;
        }

        [$archive, $label, $temporary] = $this->archive($backups);

        if ($archive === null) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Replace ALL current data (database and uploads) with {$label}?")) {
            $temporary && File::delete($archive);

            return self::FAILURE;
        }

        $this->info('Restoring… (maintenance mode while the data is replaced)');

        try {
            $ok = $restorer->run($archive, $label, backupFirst: ! $this->option('no-backup'), actor: 'console');
        } finally {
            $temporary && File::delete($archive);
        }

        $result = $status->get() ?? [];

        if (! $ok) {
            $this->error('Restore failed: '.($result['message'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info("Restored {$label} ({$result['files']} files). Run php artisan cms:check to verify.");

        return self::SUCCESS;
    }

    /**
     * The local archive to restore, its label and whether it is a temporary copy.
     *
     * @return array{0: string|null, 1: string, 2: bool}
     */
    protected function archive(Backups $backups): array
    {
        if ($this->option('disk')) {
            $backup = $backups->find((string) $this->option('disk'), (string) $this->option('path'));

            if ($backup === null) {
                $this->error('No such backup on that disk. See php artisan backup:list.');

                return [null, '', false];
            }

            $local = storage_path('app/restore-temp/'.Str::uuid().'.zip');
            File::ensureDirectoryExists(dirname($local));
            $input = $backup->stream();
            $output = fopen($local, 'wb');

            if ($output === false) {
                $this->error('Could not write a local copy of the backup.');

                return [null, '', false];
            }

            stream_copy_to_stream($input, $output);
            fclose($input);
            fclose($output);

            return [$local, basename($backup->path()), true];
        }

        $archive = (string) $this->argument('archive');

        if ($archive === '' || ! File::isFile($archive)) {
            $this->error('Give the path to a backup .zip, or --disk with --path.');

            return [null, '', false];
        }

        return [$archive, basename($archive), false];
    }
}
