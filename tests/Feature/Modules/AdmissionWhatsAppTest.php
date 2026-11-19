<?php

use App\Enums\DocumentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Enums\WhatsAppEvent;
use App\Models\AdmissionDocument;
use App\Models\AdmissionMessage;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionRegistration;
use App\Models\AdmissionRequirement;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    Storage::fake('local');
    $this->pondok = onSite(SiteKey::Pondok);
    $this->period = AdmissionPeriod::factory()->for($this->pondok)->published()->create();
    $this->kk = $this->period->documentRequirements()->create(['name' => 'Kartu Keluarga', 'file_types' => ['pdf'], 'sort_order' => 0]);
    $this->photo = $this->period->documentRequirements()->create(['name' => 'Pas Foto', 'file_types' => ['jpg'], 'sort_order' => 1]);
});

function waApplicant(AdmissionPeriod $period, array $overrides = []): AdmissionRegistration
{
    return AdmissionRegistration::query()->create([
        'site_id' => $period->site_id, 'admission_period_id' => $period->id,
        'full_name' => 'Ahmad Fauzi', 'gender' => 'L', 'birth_place' => 'Kendal', 'birth_date' => '2012-05-01',
        'previous_school' => 'SDN 1', 'parent_name' => 'Bapak Fauzi', 'parent_phone' => '0812-3456-7890',
        'address' => 'Kendal', ...$overrides,
    ]);
}

function waDocument(AdmissionRegistration $registration, AdmissionRequirement $requirement, DocumentStatus $status, ?string $note = null): AdmissionDocument
{
    $document = new AdmissionDocument(['admission_registration_id' => $registration->id, 'admission_requirement_id' => $requirement->id]);
    $document->forceFill(['status' => $status, 'note' => $note, 'uploaded_at' => now(), 'reviewed_at' => now()])->save();

    return $document;
}

test('phone numbers are converted to the international WhatsApp form', function (?string $input, ?string $expected) {
    expect(WhatsApp::normalizePhone($input))->toBe($expected);
})->with([
    ['0812-3456-7890', '6281234567890'],
    ['+62 812 3456 7890', '6281234567890'],
    ['812 3456 7890', '6281234567890'],
    ['6281234567890', '6281234567890'],
    ['12345', null],
    ['', null],
    [null, null],
]);

test('templates are filled in with the registration, its documents and the site', function () {
    $registration = waApplicant($this->period);
    waDocument($registration, $this->kk, DocumentStatus::NeedsFix, 'Foto buram, mohon dipindai ulang.');

    $needsFix = WhatsApp::message(WhatsAppEvent::NeedsFix, $registration);
    expect($needsFix)
        ->toContain('Bapak/Ibu Bapak Fauzi')
        ->toContain('*Ahmad Fauzi* (nomor '.$registration->registration_number.')')
        ->toContain('- Kartu Keluarga: Foto buram, mohon dipindai ulang.')
        ->not->toContain('Pas Foto')
        ->toContain('http://pondok.test/penerimaan/lanjutkan')
        ->toContain('Panitia Penerimaan '.$this->pondok->name);

    // The reminder also lists required documents that are not uploaded yet.
    expect(WhatsApp::message(WhatsAppEvent::Reminder, $registration))
        ->toContain('- Kartu Keluarga: Foto buram')
        ->toContain('- Pas Foto (belum diunggah)');

    // A custom template from the site settings wins.
    $this->pondok->fillSettings([...$this->pondok->allSettings(), 'whatsapp.template_accepted' => 'Selamat {wali}, {nama} diterima. Status: {status}.'])->save();
    $registration->forceFill(['status' => RegistrationStatus::Accepted])->save();
    expect(WhatsApp::message(WhatsAppEvent::Accepted, $registration->fresh()))->toBe('Selamat Bapak Fauzi, Ahmad Fauzi diterima. Status: Diterima.');
});

test('admins prepare a message, open it in WhatsApp and the send is logged', function () {
    $registration = waApplicant($this->period);
    $admin = cmsUser(Role::PondokAdmin, ['name' => 'Ustadz Panitia']);
    $this->actingAs($admin);

    Livewire::test('pages::admin.admissions.registrations')
        ->call('show', $registration->id)
        ->assertSee(['WhatsApp to the guardian', '+6281234567890', 'Registration received', 'Reminder to complete documents'])
        ->call('composeWhatsApp', 'received')
        ->assertSet('waEvent', 'received')
        ->assertSee('Open WhatsApp')
        ->assertSee('https://wa.me/6281234567890?text=', false)
        ->set('waMessage', 'Pesan yang sudah diubah admin.')
        ->call('logWhatsApp')
        ->assertSet('waEvent', '')
        ->assertSee(['Sent messages', 'Opened by Ustadz Panitia']);

    expect(AdmissionMessage::query()->sole()->only(['event', 'channel', 'status', 'phone', 'body', 'user_id']))->toBe([
        'event' => WhatsAppEvent::Received, 'channel' => 'link', 'status' => 'opened',
        'phone' => '6281234567890', 'body' => 'Pesan yang sudah diubah admin.', 'user_id' => $admin->id,
    ]);
});

test('a new status or a document fix request prepares the matching message', function () {
    $registration = waApplicant($this->period);
    $document = waDocument($registration, $this->kk, DocumentStatus::Pending);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.admissions.registrations')
        ->call('show', $registration->id)
        ->set('newStatus', RegistrationStatus::Accepted->value)
        ->call('update')
        ->assertSet('waEvent', 'accepted')
        ->assertSet('waMessage', fn (string $message) => str_contains($message, '*DITERIMA*'))
        ->call('cancelWhatsApp')
        // Saving again without a status change does not prepare anything.
        ->set('adminNotes', 'Catatan internal')
        ->call('update')
        ->assertSet('waEvent', '')
        ->call('show', $registration->id)
        ->call('startFix', $document->id)
        ->set('fixNote', 'Mohon unggah yang lebih jelas.')
        ->call('requestFix')
        ->assertSet('waEvent', 'needs_fix')
        ->assertSet('waMessage', fn (string $message) => str_contains($message, '- Kartu Keluarga: Mohon unggah yang lebih jelas.'));
});

test('an invalid phone number cannot be messaged and viewers cannot send', function () {
    $registration = waApplicant($this->period, ['parent_phone' => '12345']);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.admissions.registrations')
        ->call('show', $registration->id)
        ->assertSee('is not a valid WhatsApp number')
        ->call('composeWhatsApp', 'received')
        ->assertSet('waEvent', '')
        ->set('waEvent', 'received')
        ->set('waMessage', 'x')
        ->call('logWhatsApp')
        ->assertStatus(422);

    expect(AdmissionMessage::query()->count())->toBe(0);
});

test('templates are edited in site settings; the built-in text is not stored', function () {
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.site-settings')
        ->set('tab', 'whatsapp')
        ->assertSee(['Placeholders', '{nomor}', 'Document needs fixing'])
        ->assertSet('settings.whatsapp__template_received', WhatsAppEvent::Received->defaultTemplate())
        ->set('settings.whatsapp__template_accepted', 'Selamat, {nama} diterima.')
        ->call('save')
        ->assertHasNoErrors()
        ->call('resetTemplate', 'accepted')
        ->assertSet('settings.whatsapp__template_accepted', WhatsAppEvent::Accepted->defaultTemplate());

    $settings = $this->pondok->fresh()->settings;
    expect($settings['whatsapp']['template_accepted'])->toBe('Selamat, {nama} diterima.')
        ->and($settings['whatsapp']['template_received'] ?? null)->toBeNull();
});
