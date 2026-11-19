<?php

use App\Enums\Role;
use App\Jobs\RestoreBackup;
use App\Models\AuditLog;
use App\Services\Restore\BackupRestorer;
use App\Services\Restore\PostgresLoader;
use App\Services\Restore\RestoreStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Stand-in for psql: records the SQL it is given.
 */
class FakePostgresLoader extends PostgresLoader
{
    public ?string $sql = null;

    public bool $fail = false;

    public function check(): array
    {
        return ['ok' => true, 'message' => 'psql (PostgreSQL) 17.0'];
    }

    public function load(string $sqlFile): array
    {
        $this->sql = (string) file_get_contents($sqlFile);

        return $this->fail ? ['ok' => false, 'message' => 'ERROR: relation "users" already exists'] : ['ok' => true, 'message' => ''];
    }
}

beforeEach(function () {
    seedCms();

    // A throwaway storage folder: a restore writes into storage/app.
    $this->storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cms-restore-'.Str::random(8);
    File::ensureDirectoryExists($this->storage.'/app');
    File::ensureDirectoryExists($this->storage.'/framework/cache');
    $this->originalStorage = $this->app->storagePath();
    $this->app->useStoragePath($this->storage);

    config(['backup.backup.name' => 'cms-pondok', 'backup.backup.destination.disks' => ['local'], 'backup.backup.password' => 'rahasia']);
    $this->loader = new FakePostgresLoader;
    $this->app->instance(PostgresLoader::class, $this->loader);
});

afterEach(function () {
    if ($this->app->isDownForMaintenance()) {
        Artisan::call('up');
    }
    $this->app->useStoragePath($this->originalStorage);
    File::deleteDirectory($this->storage);
});

/**
 * @param  array<string, string>  $entries  name in zip => content
 */
function backupArchive(string $path, array $entries, ?string $password = 'rahasia'): string
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
        if ($password !== null) {
            $zip->setEncryptionName($name, ZipArchive::EM_AES_256, $password);
        }
    }
    $zip->close();

    return $path;
}

function standardBackup(string $dir, ?string $password = 'rahasia', array $extra = []): string
{
    $dump = implode("\n", [
        '\\restrict abc123',
        'SET statement_timeout = 0;',
        'SET transaction_timeout = 0;',
        'CREATE TABLE public.users (id bigint NOT NULL);',
        'ALTER TABLE public.users OWNER TO cms;',
        'COPY public.users (id) FROM stdin;',
        '1',
        '\\.',
        '\\unrestrict abc123',
        '',
    ]);

    return backupArchive($dir.'/backup.zip', [
        'db-dumps/postgresql-cms.sql.gz' => gzencode($dump),
        'var/www/cms/shared/storage/app/public/1/foto.jpg' => 'JPEGDATA',
        'var/www/cms/shared/storage/app/private/admissions/kk.pdf' => '%PDF',
        'var/www/cms/shared/storage/app/private/cms-pondok/2026-01-01-02-00-00.zip' => 'old backup',
        'etc/passwd' => 'root:x:0:0',
        ...$extra,
    ], $password);
}

test('a backup is unpacked, the database replaced with a cleaned dump, and uploads put back', function () {
    $archive = standardBackup($this->storage);
    File::ensureDirectoryExists(storage_path('app/public'));
    File::put(storage_path('app/public/kept.txt'), 'not in backup');

    $ok = app(BackupRestorer::class)->run($archive, 'backup.zip', backupFirst: false, actor: 'Tester');

    expect($ok)->toBeTrue()
        ->and(app(RestoreStatus::class)->get())->toMatchArray(['state' => 'done', 'files' => 2, 'file' => 'backup.zip', 'by' => 'Tester'])
        ->and($this->loader->sql)->toStartWith("DROP SCHEMA IF EXISTS public CASCADE;\nCREATE SCHEMA public;\n")
        ->and($this->loader->sql)->toContain('CREATE TABLE public.users')->toContain("COPY public.users (id) FROM stdin;\n1\n\\.")
        ->and($this->loader->sql)->not->toContain('OWNER TO')->not->toContain('restrict')->not->toContain('transaction_timeout')
        ->and(File::get(storage_path('app/public/1/foto.jpg')))->toBe('JPEGDATA')
        ->and(File::get(storage_path('app/private/admissions/kk.pdf')))->toBe('%PDF')
        ->and(File::exists(storage_path('app/private/cms-pondok/2026-01-01-02-00-00.zip')))->toBeFalse()
        ->and(File::get(storage_path('app/public/kept.txt')))->toBe('not in backup')
        ->and(File::isDirectory(storage_path('app/restore-temp')) ? File::directories(storage_path('app/restore-temp')) : [])->toBe([])
        ->and($this->app->isDownForMaintenance())->toBeFalse()
        ->and(AuditLog::query()->where('event', 'data.restored')->exists())->toBeTrue();
});

test('a wrong backup password stops before anything changes', function () {
    $archive = standardBackup($this->storage, password: 'lain');

    expect(app(BackupRestorer::class)->run($archive, 'backup.zip', backupFirst: false))->toBeFalse()
        ->and(app(RestoreStatus::class)->get())->toMatchArray(['state' => 'failed'])
        ->and(app(RestoreStatus::class)->get()['message'])->toContain('BACKUP_ARCHIVE_PASSWORD')
        ->and($this->loader->sql)->toBeNull()
        ->and(File::exists(storage_path('app/public/1/foto.jpg')))->toBeFalse()
        ->and($this->app->isDownForMaintenance())->toBeFalse();
});

test('unsafe paths, a missing dump or a non-zip file are refused', function () {
    $restorer = app(BackupRestorer::class);

    $unsafe = backupArchive($this->storage.'/unsafe.zip', ['db-dumps/x.sql' => 'SELECT 1;', 'storage/app/../../evil.php' => '<?php']);
    expect($restorer->run($unsafe, 'unsafe.zip', backupFirst: false))->toBeFalse()
        ->and(app(RestoreStatus::class)->get()['message'])->toContain('unsafe path');

    $noDump = backupArchive($this->storage.'/nodump.zip', ['var/storage/app/public/a.jpg' => 'x']);
    expect($restorer->run($noDump, 'nodump.zip', backupFirst: false))->toBeFalse()
        ->and(app(RestoreStatus::class)->get()['message'])->toContain('no database dump');

    File::put($this->storage.'/broken.zip', 'not a zip');
    expect($restorer->run($this->storage.'/broken.zip', 'broken.zip', backupFirst: false))->toBeFalse();

    expect($this->loader->sql)->toBeNull();
});

test('a failed database load leaves the database and files unchanged and the site online', function () {
    $this->loader->fail = true;
    $archive = standardBackup($this->storage);

    expect(app(BackupRestorer::class)->run($archive, 'backup.zip', backupFirst: false))->toBeFalse()
        ->and(app(RestoreStatus::class)->get()['message'])->toContain('left unchanged')->toContain('already exists')
        ->and(File::exists(storage_path('app/public/1/foto.jpg')))->toBeFalse()
        ->and($this->app->isDownForMaintenance())->toBeFalse();
});

test('the current data is backed up first, and a failed backup stops the restore', function () {
    Artisan::shouldReceive('call')->once()->with('backup:run', ['--disable-notifications' => true])->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('pg_dump: command not found');

    expect(app(BackupRestorer::class)->run(standardBackup($this->storage), 'backup.zip'))->toBeFalse()
        ->and(app(RestoreStatus::class)->get()['message'])->toContain('nothing was restored')->toContain('pg_dump');
    expect($this->loader->sql)->toBeNull();
});

test('the background job restores a backup from a disk', function () {
    Storage::fake('local');
    Storage::disk('local')->put('cms-pondok/2026-11-20-02-00-00.zip', File::get(standardBackup($this->storage)));
    Artisan::shouldReceive('call')->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    app()->call([new RestoreBackup('local', 'cms-pondok/2026-11-20-02-00-00.zip', null, 'Tester'), 'handle']);

    expect(app(RestoreStatus::class)->get())->toMatchArray(['state' => 'done', 'file' => '2026-11-20-02-00-00.zip'])
        ->and($this->loader->sql)->toContain('CREATE TABLE public.users');
});

test('superadmins start a restore after re-entering the password and typing the backup name', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::disk('local')->put('cms-pondok/2026-11-20-02-00-00.zip', 'zip');
    $this->actingAs(cmsUser(Role::Superadmin, ['password' => 'rahasia-admin']));

    Livewire::test('pages::admin.data')
        ->call('chooseRestore', 'local', 'cms-pondok/2026-11-20-02-00-00.zip')
        ->assertSee(['Restore 2026-11-20-02-00-00', 'PostgreSQL client (psql)', 'Type 2026-11-20-02-00-00 to confirm'])
        ->set('restorePassword', 'salah')
        ->set('restoreConfirmation', '2026-11-20')
        ->call('startRestore')
        ->assertHasErrors(['restorePassword', 'restoreConfirmation'])
        ->set('restorePassword', 'rahasia-admin')
        ->set('restoreConfirmation', '2026-11-20-02-00-00')
        ->call('startRestore')
        ->assertHasNoErrors()
        ->assertSee(['Restore of 2026-11-20-02-00-00.zip', 'Waiting for the background worker']);

    Queue::assertPushed(RestoreBackup::class, fn (RestoreBackup $job) => $job->disk === 'local'
        && $job->path === 'cms-pondok/2026-11-20-02-00-00.zip'
        && strlen((string) $job->maintenanceSecret) === 40);
    expect(Cookie::hasQueued('laravel_maintenance'))->toBeTrue()
        ->and(AuditLog::query()->where('event', 'data.restore_started')->exists())->toBeTrue();

    // A second restore cannot start while one runs.
    Livewire::test('pages::admin.data')
        ->call('chooseRestore', 'local', 'cms-pondok/2026-11-20-02-00-00.zip')
        ->set('restorePassword', 'rahasia-admin')
        ->set('restoreConfirmation', '2026-11-20-02-00-00')
        ->call('startRestore')
        ->assertHasErrors('restoreConfirmation');
    Queue::assertPushed(RestoreBackup::class, 1);
});

test('the command restores a local archive', function () {
    $archive = standardBackup($this->storage);

    $this->artisan('cms:restore', ['archive' => $archive, '--force' => true, '--no-backup' => true])
        ->expectsOutputToContain('Restored backup.zip (2 files)')
        ->assertSuccessful();

    expect(File::get(storage_path('app/public/1/foto.jpg')))->toBe('JPEGDATA');

    $this->artisan('cms:restore', ['archive' => $this->storage.'/missing.zip', '--force' => true])->assertFailed();
});
