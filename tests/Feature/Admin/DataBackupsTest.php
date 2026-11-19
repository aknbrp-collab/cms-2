<?php

use App\Enums\Role;
use App\Jobs\RunBackup;
use App\Models\AuditLog;
use App\Services\Backups;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    config(['backup.backup.name' => 'cms-pondok', 'backup.backup.destination.disks' => ['local']]);
    Storage::fake('local');
    Storage::disk('local')->put('cms-pondok/2026-11-19-02-00-00.zip', 'zip-lama');
    Storage::disk('local')->put('cms-pondok/2026-11-20-02-00-00.zip', 'zip-baru');
    Storage::disk('local')->put('secrets.txt', 'jangan');
});

test('superadmins see the backups per disk, newest first', function () {
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.data')
        ->assertOk()
        ->assertSee(['This server', '2 backups'])
        ->assertSeeInOrder(['2026-11-20-02-00-00.zip', '2026-11-19-02-00-00.zip'])
        ->assertDontSee('secrets.txt');
});

test('only superadmins have the data page', function () {
    $this->actingAs(cmsUser(Role::ManagingEditor));
    Livewire::test('pages::admin.data')->assertForbidden();

    $this->actingAs(cmsUser(Role::PondokAdmin));
    Livewire::test('pages::admin.data')->assertForbidden();
});

test('a backup runs in the background, one at a time', function () {
    Queue::fake();
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.data')
        ->call('backUp', true)
        ->assertSee('Backup running')
        ->call('backUp', false);

    Queue::assertPushed(RunBackup::class, 1);
    Queue::assertPushed(RunBackup::class, fn (RunBackup $job) => $job->onlyDb === true);
    expect(app(Backups::class)->running())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'data.backup_started')->count())->toBe(1);
});

test('the backup job records success and failure', function () {
    Artisan::shouldReceive('call')->once()->with('backup:run', ['--only-db' => true, '--disable-notifications' => true])->andReturn(0);
    Artisan::shouldReceive('call')->once()->with('backup:run', ['--disable-notifications' => true])->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('Backup completed!', 'Backup failed because: pg_dump not found');

    (new RunBackup(onlyDb: true))->handle(app(Backups::class));
    expect(app(Backups::class)->status())->toMatchArray(['state' => 'done', 'only_db' => true, 'message' => null]);

    (new RunBackup)->handle(app(Backups::class));
    expect(app(Backups::class)->status())->toMatchArray(['state' => 'failed', 'message' => 'Backup failed because: pg_dump not found']);

    $this->actingAs(cmsUser(Role::Superadmin));
    Livewire::test('pages::admin.data')->assertSee(['The last backup failed', 'pg_dump not found']);
});

test('backups download, and only backup archives can be reached', function () {
    $this->actingAs(cmsUser(Role::Superadmin));

    $response = $this->get(route('admin.data.download', ['disk' => 'local', 'path' => 'cms-pondok/2026-11-20-02-00-00.zip']))->assertOk();
    expect($response->streamedContent())->toBe('zip-baru')
        ->and(AuditLog::query()->where('event', 'data.backup_downloaded')->exists())->toBeTrue();

    $this->get(route('admin.data.download', ['disk' => 'local', 'path' => 'secrets.txt']))->assertNotFound();
    $this->get(route('admin.data.download', ['disk' => 'local', 'path' => 'cms-pondok/../secrets.txt']))->assertNotFound();
    $this->get(route('admin.data.download', ['disk' => 'public', 'path' => 'cms-pondok/2026-11-20-02-00-00.zip']))->assertNotFound();

    $this->actingAs(cmsUser(Role::ManagingEditor));
    $this->get(route('admin.data.download', ['disk' => 'local', 'path' => 'cms-pondok/2026-11-20-02-00-00.zip']))->assertForbidden();
});

test('a backup can be deleted', function () {
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.data')
        ->call('deleteBackup', 'local', 'cms-pondok/2026-11-19-02-00-00.zip')
        ->assertSee('1 backup')
        ->call('deleteBackup', 'local', 'secrets.txt')
        ->assertNotFound();

    Storage::disk('local')->assertMissing('cms-pondok/2026-11-19-02-00-00.zip');
    Storage::disk('local')->assertExists('secrets.txt');
    expect(AuditLog::query()->where('event', 'data.backup_deleted')->count())->toBe(1);
});
