<?php

use App\Enums\DocumentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\AdmissionDocument;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionRegistration;
use App\Models\AdmissionRequirement;
use App\Models\AuditLog;
use App\Models\Site;
use App\Notifications\AdmissionDocumentNeedsFix;
use App\Services\AdmissionDocuments;
use App\Support\AdmissionAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    Storage::fake('local');
    Storage::fake('public');
    $this->pondok = onSite(SiteKey::Pondok);
    $this->period = AdmissionPeriod::factory()->for($this->pondok)->published()->create();
    $this->kk = $this->period->documentRequirements()->create(['name' => 'Kartu Keluarga', 'file_types' => ['pdf', 'jpg', 'png'], 'sort_order' => 0]);
    $this->photo = $this->period->documentRequirements()->create(['name' => 'Pas Foto', 'file_types' => ['jpg', 'png'], 'max_size_kb' => 1024, 'sort_order' => 1]);
    $this->ijazah = $this->period->documentRequirements()->create(['name' => 'Ijazah', 'file_types' => ['pdf'], 'is_required' => false, 'sort_order' => 2]);
});

function applicant(AdmissionPeriod $period, array $overrides = []): AdmissionRegistration
{
    return AdmissionRegistration::query()->create([
        'site_id' => $period->site_id, 'admission_period_id' => $period->id,
        'full_name' => 'Ahmad Fauzi', 'gender' => 'L', 'birth_place' => 'Kendal', 'birth_date' => '2012-05-01',
        'previous_school' => 'SDN 1', 'parent_name' => 'Bapak Fauzi', 'parent_phone' => '081234567890',
        'address' => 'Kendal', 'email' => 'wali@example.com', ...$overrides,
    ]);
}

function pdfUpload(string $name = 'kk.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF");
}

/**
 * Open the document step for a registration, as after entering number + birth date.
 */
function withAccess(AdmissionRegistration $registration): void
{
    test()->withSession([AdmissionAccess::SESSION_KEY => ['registration_id' => $registration->id, 'expires_at' => now()->addHour()->timestamp]]);
}

test('registering for a period with documents leads to the upload step', function () {
    $this->get('http://pondok.test/penerimaan/daftar')->assertOk()->assertSee('Unggah berkas');
    $this->travel(10)->seconds();

    $this->post('http://pondok.test/penerimaan/daftar', [
        'full_name' => 'Ahmad Fauzi', 'gender' => 'L', 'birth_place' => 'Kendal', 'birth_date' => '2012-05-01',
        'previous_school' => 'SDN 1', 'program' => $this->period->programs[0] ?? null, 'parent_name' => 'Bapak Fauzi',
        'parent_phone' => '081234567890', 'address' => 'Kendal', 'agree' => '1',
    ])->assertRedirect('http://pondok.test/penerimaan/berkas');

    $registration = AdmissionRegistration::query()->sole();

    $this->get('http://pondok.test/penerimaan/berkas')
        ->assertOk()
        ->assertSee($registration->registration_number)
        ->assertSee('Kartu Keluarga')
        ->assertSee('Pas Foto')
        ->assertSee('PDF, JPG, PNG · maks. 5 MB')
        ->assertSee('0 dari 3 berkas sudah diunggah.');
});

test('applicants upload a pdf and an image, stored privately', function () {
    $registration = applicant($this->period);
    withAccess($registration);

    $this->post("http://pondok.test/penerimaan/berkas/{$this->kk->id}", ['file' => pdfUpload()])
        ->assertRedirect()
        ->assertSessionHas('uploaded');
    $this->post("http://pondok.test/penerimaan/berkas/{$this->photo->id}", ['file' => UploadedFile::fake()->image('foto.jpg', 300, 400)])
        ->assertRedirect();

    $documents = $registration->documents()->with('media')->get();
    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('status')->unique()->all())->toBe([DocumentStatus::Pending]);

    $photo = $documents->firstWhere('admission_requirement_id', $this->photo->id)->file();
    expect($photo->disk)->toBe('local')
        ->and($photo->file_name)->not->toContain('foto')
        ->and(Storage::disk('local')->exists($photo->getPathRelativeToRoot()))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(getimagesize($photo->getPath()))->not->toBeFalse();

    $this->get('http://pondok.test/penerimaan/berkas')->assertSee('2 dari 3 berkas sudah diunggah.')->assertSee('Menunggu pemeriksaan');
});

test('uploaded photos lose their exif metadata', function () {
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();
    // Insert an APP1 "Exif" segment (minimal TIFF header + a marker string) after the SOI marker.
    $exif = "Exif\0\0II*\0\x08\0\0\0\0\0GPS-SECRET-LOCATION";
    $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    expect($withExif)->toContain('GPS-SECRET-LOCATION');

    $document = app(AdmissionDocuments::class)->store(applicant($this->period), $this->photo, UploadedFile::fake()->createWithContent('foto.jpg', $withExif));

    $stored = (string) file_get_contents($document->file()->getPath());
    expect($stored)->not->toContain('GPS-SECRET-LOCATION')->not->toContain('Exif')
        ->and(getimagesizefromstring($stored))->not->toBeFalse();
});

test('files are checked by content, type and size', function () {
    withAccess(applicant($this->period));

    // A real file: fake uploads report the MIME type of their name, not their content.
    $path = tempnam(sys_get_temp_dir(), 'kk');
    file_put_contents($path, 'just text, not a PDF');
    $this->post("http://pondok.test/penerimaan/berkas/{$this->kk->id}", ['file' => new UploadedFile($path, 'kk.pdf', null, null, true)])
        ->assertSessionHasErrors(['file'], null, "upload_{$this->kk->id}");
    $this->post("http://pondok.test/penerimaan/berkas/{$this->ijazah->id}", ['file' => UploadedFile::fake()->image('ijazah.jpg')])
        ->assertSessionHasErrors(['file'], null, "upload_{$this->ijazah->id}");
    $this->post("http://pondok.test/penerimaan/berkas/{$this->photo->id}", ['file' => UploadedFile::fake()->image('foto.jpg')->size(2048)])
        ->assertSessionHasErrors(['file'], null, "upload_{$this->photo->id}");

    expect(AdmissionDocument::query()->count())->toBe(0);
});

test('javascript uploads get json answers', function () {
    withAccess(applicant($this->period));

    $this->postJson("http://pondok.test/penerimaan/berkas/{$this->kk->id}", ['file' => pdfUpload()])->assertOk()->assertJson(['ok' => true]);
    $this->postJson("http://pondok.test/penerimaan/berkas/{$this->photo->id}", ['file' => pdfUpload('foto.pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
});

test('accepted files are final, files to fix stay replaceable after closing', function () {
    $registration = applicant($this->period);
    $documents = app(AdmissionDocuments::class);
    $kk = $documents->store($registration, $this->kk, pdfUpload());
    $photo = $documents->store($registration, $this->photo, UploadedFile::fake()->image('foto.jpg'));
    $admin = cmsUser(Role::PondokAdmin);
    $documents->review($kk, DocumentStatus::Accepted, null, $admin);
    $documents->review($photo, DocumentStatus::NeedsFix, 'Foto buram', $admin);
    $this->period->update(['closes_on' => today()->subDay(), 'opens_on' => today()->subMonth()]);
    withAccess($registration);

    $this->post("http://pondok.test/penerimaan/berkas/{$this->kk->id}", ['file' => pdfUpload()])
        ->assertSessionHasErrors(['file'], null, "upload_{$this->kk->id}");
    $this->post("http://pondok.test/penerimaan/berkas/{$this->ijazah->id}", ['file' => pdfUpload('ijazah.pdf')])
        ->assertSessionHasErrors(['file'], null, "upload_{$this->ijazah->id}");
    $this->post("http://pondok.test/penerimaan/berkas/{$this->photo->id}", ['file' => UploadedFile::fake()->image('foto-baru.jpg')])
        ->assertSessionHasNoErrors();

    expect($photo->fresh()->status)->toBe(DocumentStatus::Pending)->and($kk->fresh()->status)->toBe(DocumentStatus::Accepted);
    $this->get('http://pondok.test/penerimaan/berkas')->assertSee('Unggah berkas sudah ditutup untuk pendaftaran ini.');
});

test('applicants come back with their number and birth date', function () {
    $registration = applicant($this->period);

    $this->get('http://pondok.test/penerimaan/berkas')->assertRedirect('http://pondok.test/penerimaan/lanjutkan');
    $this->post("http://pondok.test/penerimaan/berkas/{$this->kk->id}", ['file' => pdfUpload()])->assertForbidden();

    $this->post('http://pondok.test/penerimaan/lanjutkan', ['registration_number' => $registration->registration_number, 'birth_date' => '2012-05-02'])
        ->assertSessionHasErrors('registration_number');
    $this->post('http://pondok.test/penerimaan/lanjutkan', ['registration_number' => strtolower($registration->registration_number), 'birth_date' => '2012-05-01'])
        ->assertRedirect('http://pondok.test/penerimaan/berkas');

    $this->get('http://pondok.test/penerimaan/berkas')->assertOk()->assertSee($registration->registration_number);
});

test('a registration of another site cannot be opened', function () {
    $mahadPeriod = AdmissionPeriod::factory()->for(Site::findByKey(SiteKey::Mahad))->published()->create();
    $registration = applicant($mahadPeriod);

    $this->post('http://pondok.test/penerimaan/lanjutkan', ['registration_number' => $registration->registration_number, 'birth_date' => '2012-05-01'])
        ->assertSessionHasErrors('registration_number');
    $this->get('http://pondok.test/penerimaan/berkas')->assertRedirect('http://pondok.test/penerimaan/lanjutkan');
});

test('guessing is throttled', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('http://pondok.test/penerimaan/lanjutkan', ['registration_number' => "REG-0000-{$attempt}", 'birth_date' => '2012-05-01']);
    }

    $this->post('http://pondok.test/penerimaan/lanjutkan', ['registration_number' => 'REG-0000-X', 'birth_date' => '2012-05-01'])->assertTooManyRequests();
});

test('staff view files of their own site only, and downloads are audited', function () {
    $registration = applicant($this->period);
    $photo = app(AdmissionDocuments::class)->store($registration, $this->photo, UploadedFile::fake()->image('foto.jpg'));
    $kk = app(AdmissionDocuments::class)->store($registration, $this->kk, pdfUpload());

    $this->actingAs(cmsUser(Role::PondokAdmin));
    $this->get(route('admin.admissions.document', $photo))->assertOk()->assertHeader('Content-Disposition', 'inline; filename="pas-foto-'.$registration->registration_number.'.jpg"');
    $this->get(route('admin.admissions.document', $kk))->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="kartu-keluarga-'.$registration->registration_number.'.pdf"');
    expect(AuditLog::query()->where('event', 'admission.document_downloaded')->count())->toBe(1);

    $this->actingAs(cmsUser(Role::MahadAdmin))->get(route('admin.admissions.document', $photo))->assertNotFound();
    $this->actingAs(cmsUser(Role::SocialMediaManager))->get(route('admin.admissions.document', $photo))->assertForbidden();
});

test('staff accept documents or send them back with a note and an email', function () {
    Notification::fake();
    $registration = applicant($this->period);
    $kk = app(AdmissionDocuments::class)->store($registration, $this->kk, pdfUpload());
    $photo = app(AdmissionDocuments::class)->store($registration, $this->photo, UploadedFile::fake()->image('foto.jpg'));
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.admissions.registrations')
        ->call('show', $registration->id)
        ->assertSee('Kartu Keluarga')
        ->call('acceptDocument', $kk->id)
        ->call('startFix', $photo->id)
        ->call('requestFix')
        ->assertHasErrors('fixNote')
        ->set('fixNote', 'Foto buram, mohon foto ulang.')
        ->call('requestFix')
        ->assertHasNoErrors();

    expect($kk->fresh()->status)->toBe(DocumentStatus::Accepted)
        ->and($photo->fresh())->status->toBe(DocumentStatus::NeedsFix)->note->toBe('Foto buram, mohon foto ulang.');

    Notification::assertSentOnDemand(AdmissionDocumentNeedsFix::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'wali@example.com'
        && str_contains($notification->toMail($notifiable)->actionUrl, 'pondok.test/penerimaan/lanjutkan'));

    $this->actingAs(cmsUser(Role::SocialMediaManager));
    Livewire::test('pages::admin.admissions.registrations')->assertForbidden();
});

test('registrations filter by document completeness', function () {
    $complete = applicant($this->period, ['full_name' => 'Santri Lengkap']);
    $missing = applicant($this->period, ['full_name' => 'Santri Kurang']);
    $toFix = applicant($this->period, ['full_name' => 'Santri Perbaikan']);
    $documents = app(AdmissionDocuments::class);
    foreach ([$complete, $toFix] as $registration) {
        $documents->store($registration, $this->kk, pdfUpload());
        $documents->store($registration, $this->photo, UploadedFile::fake()->image('foto.jpg'));
    }
    $documents->review($toFix->documents()->first(), DocumentStatus::NeedsFix, 'Ulangi', cmsUser(Role::PondokAdmin));
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.admissions.registrations')
        ->set('documents', 'complete')->assertSee('Santri Lengkap')->assertDontSee('Santri Kurang')->assertDontSee('Santri Perbaikan')
        ->set('documents', 'incomplete')->assertSee('Santri Kurang')->assertDontSee('Santri Lengkap')
        ->set('documents', 'needs_fix')->assertSee('Santri Perbaikan')->assertDontSee('Santri Lengkap');

    expect($this->get(route('admin.admissions.export'))->streamedContent())
        ->toContain('Berkas')
        ->toContain('2/3 diunggah, 0 diterima, 1 perlu perbaikan');
});

test('the period editor manages document requirements', function () {
    $this->actingAs(cmsUser(Role::ManagingEditor));

    $component = Livewire::test('pages::admin.admissions.edit', ['period' => $this->period])
        ->assertSet('documentRows.0.name', 'Kartu Keluarga')
        ->call('addDocument')
        ->set('documentRows.3.name', 'Surat Sehat')
        ->set('documentRows.3.file_types', ['pdf'])
        ->set('documentRows.3.max_mb', 2)
        ->call('moveDocument', 3, -1)
        ->call('save')
        ->assertHasNoErrors();

    $names = $this->period->documentRequirements()->pluck('name')->all();
    expect($names)->toBe(['Kartu Keluarga', 'Pas Foto', 'Surat Sehat', 'Ijazah'])
        ->and(AdmissionRequirement::query()->where('name', 'Surat Sehat')->value('max_size_kb'))->toBe(2048);

    app(AdmissionDocuments::class)->store(applicant($this->period), $this->kk, pdfUpload());
    $component->call('removeDocument', 0)->call('save')->assertHasErrors('documentRows');
    expect($this->kk->fresh())->not->toBeNull();

    $component->set('documentRows', [])->call('save')->assertHasErrors('documentRows');
});

test('without document requirements registration ends after the form', function () {
    $this->period->documentRequirements()->delete();
    $this->travel(10)->seconds();
    session(['registration_form_started' => now()->subSeconds(10)->timestamp]);

    $response = $this->post('http://pondok.test/penerimaan/daftar', [
        'full_name' => 'Ahmad Fauzi', 'gender' => 'L', 'birth_place' => 'Kendal', 'birth_date' => '2012-05-01',
        'previous_school' => 'SDN 1', 'program' => $this->period->programs[0] ?? null, 'parent_name' => 'Bapak Fauzi',
        'parent_phone' => '081234567890', 'address' => 'Kendal', 'agree' => '1',
    ]);

    expect($response->headers->get('Location'))->toContain('/penerimaan/selesai/');
    expect(AdmissionRegistration::query()->sole()->status)->toBe(RegistrationStatus::New);
});
