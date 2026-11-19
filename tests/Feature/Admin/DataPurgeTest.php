<?php

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Jobs\PurgeData;
use App\Models\AdmissionDocument;
use App\Models\AdmissionMessage;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionRegistration;
use App\Models\AuditLog;
use App\Models\IntegrationLog;
use App\Models\Site;
use App\Services\DataPurge;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    Storage::fake('local');
    $this->travelTo(Carbon::parse('2026-11-21 10:00'));

    $pondok = Site::findByKey(SiteKey::Pondok);
    $mahad = Site::findByKey(SiteKey::Mahad);
    $this->oldPondok = AdmissionPeriod::factory()->for($pondok)->create(['title' => 'Gelombang Lama', 'opens_on' => '2025-01-01', 'closes_on' => '2025-06-30']);
    $this->oldMahad = AdmissionPeriod::factory()->for($mahad)->create(['title' => 'PMB Lama', 'opens_on' => '2025-02-01', 'closes_on' => '2025-07-31']);
    $this->current = AdmissionPeriod::factory()->for($pondok)->create(['title' => 'Gelombang Baru', 'opens_on' => '2026-10-01', 'closes_on' => '2026-12-31']);
    $requirement = $this->oldPondok->documentRequirements()->create(['name' => 'Kartu Keluarga', 'file_types' => ['pdf'], 'sort_order' => 0]);

    $this->oldA = purgeApplicant($this->oldPondok, 'Ahmad Lama');
    $this->oldB = purgeApplicant($this->oldPondok, 'Budi Lama');
    $this->oldC = purgeApplicant($this->oldMahad, 'Citra Lama');
    $this->recent = purgeApplicant($this->current, 'Dewi Baru');

    foreach ([$this->oldA, $this->oldB] as $registration) {
        $document = new AdmissionDocument(['admission_registration_id' => $registration->id, 'admission_requirement_id' => $requirement->id]);
        $document->forceFill(['status' => DocumentStatus::Accepted, 'uploaded_at' => now()])->save();
        $document->addMedia(UploadedFile::fake()->create('kk.pdf', 10))->toMediaCollection('file');
    }

    $this->oldA->messages()->create(['event' => 'accepted', 'channel' => 'link', 'status' => 'opened', 'phone' => '6281234567890', 'body' => 'x']);
    $old = $this->recent->messages()->create(['event' => 'received', 'channel' => 'link', 'status' => 'opened', 'phone' => '6281234567890', 'body' => 'lama']);
    $old->forceFill(['created_at' => '2025-01-10'])->save();
    $this->recent->messages()->create(['event' => 'verified', 'channel' => 'link', 'status' => 'opened', 'phone' => '6281234567890', 'body' => 'baru']);

    $log = IntegrationLog::query()->create(['system' => 'siakad', 'direction' => 'inbound', 'action' => 'article', 'status' => 'success']);
    $log->forceFill(['created_at' => '2025-03-01'])->save();
    IntegrationLog::query()->create(['system' => 'siakad', 'direction' => 'inbound', 'action' => 'article', 'status' => 'success']);
});

function purgeApplicant(AdmissionPeriod $period, string $name): AdmissionRegistration
{
    return AdmissionRegistration::withoutSiteScope()->create([
        'site_id' => $period->site_id, 'admission_period_id' => $period->id,
        'full_name' => $name, 'gender' => 'L', 'birth_place' => 'Kendal', 'birth_date' => '2010-05-01',
        'previous_school' => 'SDN 1', 'parent_name' => 'Wali '.$name, 'parent_phone' => '081234567890', 'address' => 'Kendal',
    ]);
}

test('the preview counts registrations of both sites from periods closed before the date', function () {
    $preview = app(DataPurge::class)->preview('admissions', Carbon::parse('2026-01-01'));

    expect($preview['total'])->toBe(3)
        ->and($preview['details'])->toBe(['documents' => 2, 'messages' => 1])
        ->and(array_column($preview['periods'], 'registrations', 'site'))->toBe([Site::findByKey(SiteKey::Pondok)->name => 2, Site::findByKey(SiteKey::Mahad)->name => 1])
        ->and($preview['periods'][0]['title'])->toStartWith('Gelombang Lama');
});

test('purging admissions removes registrations, documents with their files, and messages, after a backup', function () {
    $files = AdmissionDocument::query()->with('media')->get()->map(fn ($document) => $document->getFirstMedia('file')->getPathRelativeToRoot());
    $files->each(fn ($path) => Storage::disk('local')->assertExists($path));
    Artisan::shouldReceive('call')->once()->with('backup:run', ['--disable-notifications' => true])->andReturn(0);

    (new PurgeData('admissions', '2026-01-01', 'Tester'))->handle(app(DataPurge::class));

    expect(PurgeData::status())->toMatchArray(['state' => 'done', 'counts' => ['registrations' => 3, 'documents' => 2, 'messages' => 1]])
        ->and(AdmissionRegistration::withoutSiteScope()->pluck('full_name')->all())->toBe(['Dewi Baru'])
        ->and(AdmissionDocument::query()->count())->toBe(0)
        ->and(AdmissionPeriod::withoutSiteScope()->count())->toBe(3)
        ->and(AuditLog::query()->where('event', 'data.purged')->sole()->meta)->toMatchArray(['kind' => 'admissions', 'by' => 'Tester']);
    $files->each(fn ($path) => Storage::disk('local')->assertMissing($path));

    // The audit log notes the deletions without the personal data.
    expect(AuditLog::query()->where('event', 'deleted')->where('subject_type', 'admission_registration')->pluck('old_values')->toJson())
        ->not->toContain('Ahmad Lama')->not->toContain('Wali Ahmad');
});

test('nothing is deleted when the backup fails', function () {
    Artisan::shouldReceive('call')->once()->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('pg_dump: not found');

    (new PurgeData('admissions', '2026-01-01'))->handle(app(DataPurge::class));

    expect(PurgeData::status())->toMatchArray(['state' => 'failed'])
        ->and(PurgeData::status()['message'])->toContain('nothing was deleted')
        ->and(AdmissionRegistration::withoutSiteScope()->count())->toBe(4);
});

test('old WhatsApp history and integration logs are purged by date', function () {
    $purge = app(DataPurge::class);

    expect($purge->preview('messages', Carbon::parse('2026-01-01'))['total'])->toBe(1)
        ->and($purge->purge('messages', Carbon::parse('2026-01-01')))->toBe(['messages' => 1])
        ->and(AdmissionMessage::query()->pluck('body')->sort()->values()->all())->toBe(['baru', 'x'])
        ->and($purge->purge('integration_logs', Carbon::parse('2026-01-01')))->toBe(['integration_logs' => 1])
        ->and(IntegrationLog::query()->count())->toBe(1);
});

test('superadmins preview and start a clean-up after re-entering the password and typing DELETE', function () {
    Queue::fake();
    $this->actingAs(cmsUser(Role::Superadmin, ['password' => 'rahasia-admin']));

    Livewire::test('pages::admin.data')
        ->assertSet('purgeBefore', '2025-11-21')
        ->set('purgeBefore', '2026-01-01')
        ->call('previewPurge')
        ->assertSee(['3 registrations', '2 documents', 'will be deleted permanently', 'Gelombang Lama', 'PMB Lama'])
        ->set('purgePassword', 'salah')
        ->set('purgeConfirmation', 'delete')
        ->call('startPurge')
        ->assertHasErrors(['purgePassword', 'purgeConfirmation'])
        ->set('purgePassword', 'rahasia-admin')
        ->set('purgeConfirmation', 'DELETE')
        ->call('startPurge')
        ->assertHasNoErrors()
        ->assertSee('Clean-up running');

    Queue::assertPushed(PurgeData::class, fn (PurgeData $job) => $job->kind === 'admissions' && $job->before === '2026-01-01');

    Livewire::test('pages::admin.data')
        ->set('purgeKind', 'integration_logs')
        ->assertSet('purgeBefore', today()->subDays(config('cms.retention.integration_logs'))->toDateString())
        ->set('purgeBefore', '2027-01-01')
        ->call('previewPurge')
        ->assertHasErrors('purgeBefore');

    expect(AdmissionRegistration::withoutSiteScope()->count())->toBe(4);
});
