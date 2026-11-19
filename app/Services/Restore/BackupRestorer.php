<?php

namespace App\Services\Restore;

use App\Support\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Throwable;
use ZipArchive;

/**
 * Restores a spatie/laravel-backup archive: database (atomically, with
 * psql) and the uploaded files under storage/app.
 *
 * Order: checks → backup of the current state → unpack and verify into a
 * staging folder → maintenance mode → database → files → back online.
 * Nothing live changes until the archive has been read completely.
 */
class BackupRestorer
{
    public function __construct(protected PostgresLoader $loader, protected RestoreStatus $status) {}

    /**
     * Server requirements for a restore.
     *
     * @return list<array{label: string, ok: bool, message: string}>
     */
    public function checks(): array
    {
        $psql = $this->loader->check();

        return [
            ['label' => __('PostgreSQL client (psql)'), 'ok' => $psql['ok'], 'message' => $psql['message']],
            [
                'label' => __('Backup password'),
                'ok' => filled(config('backup.backup.password')),
                'message' => filled(config('backup.backup.password')) ? __('Set') : __('BACKUP_ARCHIVE_PASSWORD is not set, so encrypted backups cannot be opened.'),
            ],
            [
                'label' => __('Zip with AES encryption'),
                'ok' => extension_loaded('zip'),
                'message' => extension_loaded('zip') ? __('Available') : __('The PHP zip extension is missing.'),
            ],
        ];
    }

    public function ready(): bool
    {
        return collect($this->checks())->every(fn (array $check) => $check['ok']);
    }

    /**
     * Restore an archive on the local filesystem. Progress goes to
     * RestoreStatus; returns whether it succeeded.
     */
    public function run(string $archive, string $label, bool $backupFirst = true, ?string $maintenanceSecret = null, ?string $actor = null): bool
    {
        $workDir = storage_path('app/restore-temp/'.Str::uuid());
        $isDown = false;

        $this->status->update(['state' => 'checking', 'file' => $label, 'by' => $actor, 'started_at' => now()->toIso8601String(), 'message' => null, 'finished_at' => null]);

        try {
            foreach ($this->checks() as $check) {
                if (! $check['ok']) {
                    throw new RuntimeException($check['label'].': '.$check['message']);
                }
            }

            if ($backupFirst) {
                $this->status->update(['state' => 'backing_up']);

                if (Artisan::call('backup:run', ['--disable-notifications' => true]) !== 0) {
                    throw new RuntimeException(__('The backup of the current data failed, so nothing was restored: :output', ['output' => Str::limit(trim(Artisan::output()), 300)]));
                }
            }

            $this->status->update(['state' => 'preparing']);
            ['sql' => $dump, 'files' => $fileCount] = $this->stage($archive, $workDir);
            $sqlFile = $this->prepareSql($dump, $workDir.'/restore.sql');

            Artisan::call('down', array_filter(['--secret' => $maintenanceSecret, '--retry' => 60]));
            $isDown = true;

            $this->status->update(['state' => 'restoring_database']);
            $loaded = $this->loader->load($sqlFile);

            if (! $loaded['ok']) {
                throw new RuntimeException(__('The database could not be restored, so it was left unchanged: :error', ['error' => $loaded['message']]));
            }

            $this->status->update(['state' => 'restoring_files']);
            $copied = $this->placeFiles($workDir.'/files');

            Artisan::call('cache:clear');
            Audit::record('data.restored', meta: ['file' => $label, 'by' => $actor, 'files' => $copied]);

            $this->status->update(['state' => 'done', 'files' => $copied, 'finished_at' => now()->toIso8601String()]);

            return true;
        } catch (Throwable $exception) {
            $this->status->update(['state' => 'failed', 'message' => Str::limit($exception->getMessage(), 700), 'finished_at' => now()->toIso8601String()]);

            return false;
        } finally {
            if ($isDown) {
                Artisan::call('up');
            }

            File::deleteDirectory($workDir);
        }
    }

    /**
     * Unpack the archive into $dir: the database dump into db/, files that
     * lived under storage/app into files/. Entries are checked so nothing is
     * written outside $dir, and every entry must read back completely (a
     * wrong password or a damaged archive stops here).
     *
     * @return array{sql: string, files: int}
     */
    public function stage(string $archive, string $dir): array
    {
        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            throw new RuntimeException(__('The file is not a readable backup archive.'));
        }

        if (filled(config('backup.backup.password'))) {
            $zip->setPassword((string) config('backup.backup.password'));
        }

        $backupName = (string) config('backup.backup.name');
        $skip = ['backup-temp/', 'restore-temp/', 'restore-status.json', $backupName.'/', 'private/'.$backupName.'/', 'private/backup-temp/'];
        $dump = null;
        $files = 0;

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));

                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                $name = (string) preg_replace('#^([A-Za-z]:)?/+#', '', $name);

                if (in_array('..', explode('/', $name), true)) {
                    throw new RuntimeException(__('The archive contains an unsafe path: :path', ['path' => $name]));
                }

                if (str_starts_with($name, 'db-dumps/')) {
                    $target = $dir.'/db/'.basename($name);
                    $dump = $target;
                } elseif (($position = strpos('/'.$name, '/storage/app/')) !== false) {
                    $relative = substr('/'.$name, $position + strlen('/storage/app/'));

                    if ($relative === '' || Str::startsWith($relative, $skip)) {
                        continue;
                    }

                    $target = $dir.'/files/'.$relative;
                    $files++;
                } else {
                    continue;
                }

                $this->extractEntry($zip, $index, (int) ($stat['size'] ?? 0), $target);
            }
        } finally {
            $zip->close();
        }

        if ($dump === null) {
            throw new RuntimeException(__('The archive has no database dump (db-dumps/).'));
        }

        return ['sql' => $dump, 'files' => $files];
    }

    /**
     * The dump as one SQL file that replaces the public schema. Ownership
     * statements are dropped so a backup from another server (or database
     * user) still loads; psql meta-commands of newer pg_dump versions too.
     */
    public function prepareSql(string $dump, string $target): string
    {
        $input = str_ends_with($dump, '.gz') ? gzopen($dump, 'rb') : fopen($dump, 'rb');
        $output = fopen($target, 'wb');

        if ($input === false || $output === false) {
            throw new RuntimeException(__('The database dump could not be read.'));
        }

        fwrite($output, "DROP SCHEMA IF EXISTS public CASCADE;\nCREATE SCHEMA public;\n");

        $read = str_ends_with($dump, '.gz') ? 'gzgets' : 'fgets';
        $eof = str_ends_with($dump, '.gz') ? 'gzeof' : 'feof';

        while (! $eof($input)) {
            $line = $read($input);

            if ($line === false) {
                break;
            }

            if (preg_match('/^ALTER [A-Z ]+ .+ OWNER TO .+;\s*$/', $line)
                || preg_match('/^\\\\(un)?restrict\b/', $line)
                || str_starts_with($line, 'SET transaction_timeout')) {
                continue;
            }

            fwrite($output, $line);
        }

        str_ends_with($dump, '.gz') ? gzclose($input) : fclose($input);
        fclose($output);

        return $target;
    }

    /**
     * Copy the staged files over storage/app. Files that are not in the
     * backup stay where they are.
     */
    public function placeFiles(string $stagedDir): int
    {
        if (! File::isDirectory($stagedDir)) {
            return 0;
        }

        $copied = 0;

        foreach (Finder::create()->files()->ignoreDotFiles(false)->in($stagedDir) as $file) {
            $target = storage_path('app/'.str_replace('\\', '/', $file->getRelativePathname()));
            File::ensureDirectoryExists(dirname($target));
            File::copy($file->getPathname(), $target);
            $copied++;
        }

        return $copied;
    }

    protected function extractEntry(ZipArchive $zip, int $index, int $size, string $target): void
    {
        File::ensureDirectoryExists(dirname($target));
        $input = $zip->getStream((string) $zip->getNameIndex($index));
        $output = fopen($target, 'wb');

        if ($input === false || $output === false) {
            throw new RuntimeException(__('The archive could not be read. Is the backup password (BACKUP_ARCHIVE_PASSWORD) the one used for this backup?'));
        }

        $written = stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);

        if ($written !== $size) {
            throw new RuntimeException(__('The archive could not be read. Is the backup password (BACKUP_ARCHIVE_PASSWORD) the one used for this backup?'));
        }
    }
}
