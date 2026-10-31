<?php

use App\Enums\RegistrationStatus;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionRegistration;
use App\Models\AuditLog;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    $this->pondok = onSite(SiteKey::Pondok);
    $this->period = AdmissionPeriod::factory()->for($this->pondok)->published()->create();
});

function registrationData(array $overrides = []): array
{
    return [
        'full_name' => 'Ahmad Fauzi',
        'gender' => 'L',
        'birth_place' => 'Kendal',
        'birth_date' => '2012-05-01',
        'previous_school' => 'SDN 1 Sukorejo',
        'program' => 'Tahfidz',
        'parent_name' => 'Bapak Fauzi',
        'parent_phone' => '081234567890',
        'address' => 'Jl. Merdeka 1, Kendal',
        'agree' => '1',
        ...$overrides,
    ];
}

test('the admissions page shows the open period', function () {
    $this->get('http://pondok.test/penerimaan')
        ->assertOk()
        ->assertSee('Gelombang I')
        ->assertSee('Daftar Online Sekarang')
        ->assertSee('Tes masuk');
});

test('a visitor registers online and gets a registration number', function () {
    $this->get('http://pondok.test/penerimaan/daftar')->assertOk()->assertSee('Formulir Pendaftaran');
    $this->travel(10)->seconds();

    $response = $this->post('http://pondok.test/penerimaan/daftar', registrationData());

    $registration = AdmissionRegistration::query()->sole();
    $response->assertRedirect();
    expect($registration->site_id)->toBe($this->pondok->id)
        ->and($registration->admission_period_id)->toBe($this->period->id)
        ->and($registration->status)->toBe(RegistrationStatus::New)
        ->and($registration->registration_number)->toStartWith('REG-');

    $this->get($response->headers->get('Location'))->assertOk()->assertSee($registration->registration_number);
    $this->get('http://pondok.test/penerimaan/selesai/'.$registration->registration_number)->assertForbidden();
});

test('spam submissions are rejected', function () {
    $this->get('http://pondok.test/penerimaan/daftar');

    $this->post('http://pondok.test/penerimaan/daftar', registrationData())->assertSessionHasErrors('full_name');

    $this->travel(10)->seconds();
    $this->post('http://pondok.test/penerimaan/daftar', registrationData(['website' => 'http://spam.example']))->assertSessionHasErrors('website');

    expect(AdmissionRegistration::query()->count())->toBe(0);
});

test('registration input is validated', function () {
    $this->get('http://pondok.test/penerimaan/daftar');
    $this->travel(10)->seconds();

    $this->post('http://pondok.test/penerimaan/daftar', registrationData([
        'parent_phone' => '12345',
        'program' => 'Tidak Ada',
        'agree' => null,
    ]))->assertSessionHasErrors(['parent_phone', 'program', 'agree']);
});

test('registration is closed outside the period or when disabled', function () {
    $this->period->update(['accepts_online_registration' => false]);
    $this->get('http://pondok.test/penerimaan/daftar')->assertNotFound();

    $this->period->update(['accepts_online_registration' => true, 'opens_on' => today()->subMonths(2), 'closes_on' => today()->subDay()]);
    $this->get('http://pondok.test/penerimaan/daftar')->assertNotFound();
    $this->post('http://pondok.test/penerimaan/daftar', registrationData())->assertNotFound();
});

test('registrations are only visible to admins of the same site', function () {
    $registration = AdmissionRegistration::query()->create([...registrationData(), 'site_id' => $this->pondok->id, 'admission_period_id' => $this->period->id]);

    $this->actingAs(cmsUser(Role::PondokAdmin));
    Livewire::test('pages::admin.admissions.registrations')->assertSee('Ahmad Fauzi');

    $this->actingAs(cmsUser(Role::MahadAdmin));
    onSite(SiteKey::Mahad);
    Livewire::test('pages::admin.admissions.registrations')
        ->assertDontSee('Ahmad Fauzi')
        ->call('show', $registration->id)
        ->assertNotFound();
});

test('staff update the registration status without leaking personal data to the audit log', function () {
    $registration = AdmissionRegistration::query()->create([...registrationData(), 'site_id' => $this->pondok->id, 'admission_period_id' => $this->period->id]);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.admissions.registrations')
        ->call('show', $registration->id)
        ->set('newStatus', RegistrationStatus::Accepted->value)
        ->set('adminNotes', 'Lulus tes')
        ->call('update')
        ->assertHasNoErrors();

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Accepted);

    $logs = AuditLog::query()->where('auditable_id', $registration->id)->get();
    expect($logs->toJson())->not->toContain('Ahmad Fauzi')->not->toContain('081234567890');
});

test('registrations export to csv with formula injection guarded', function () {
    AdmissionRegistration::query()->create([...registrationData(['full_name' => '=HYPERLINK("x")']), 'site_id' => $this->pondok->id, 'admission_period_id' => $this->period->id]);

    $this->actingAs(cmsUser(Role::PondokAdmin));
    $response = $this->get(route('admin.admissions.export'))->assertOk();

    expect($response->streamedContent())
        ->toContain('Nama Lengkap')
        ->toContain("'=HYPERLINK")
        ->toContain('081234567890');

    $this->actingAs(cmsUser(Role::SocialMediaManager))->get(route('admin.admissions.export'))->assertForbidden();
});

test('an admission period is created and edited by site admins', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.admissions.edit')
        ->set('title', 'Gelombang III')
        ->set('academicYear', '2026/2027')
        ->set('steps', [['title' => 'Daftar', 'description' => 'Online'], ['title' => '', 'description' => '']])
        ->set('programs', "Reguler\nTahfidz\n")
        ->call('save')
        ->assertHasNoErrors();

    $period = AdmissionPeriod::query()->where('title', 'Gelombang III')->sole();

    expect($period->steps)->toHaveCount(1)
        ->and($period->programs)->toBe(['Reguler', 'Tahfidz'])
        ->and($period->is_published)->toBeFalse();
});
