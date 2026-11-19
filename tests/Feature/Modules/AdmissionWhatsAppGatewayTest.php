<?php

use App\Enums\DocumentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Enums\WhatsAppEvent;
use App\Jobs\SendAdmissionWhatsApp;
use App\Models\AdmissionDocument;
use App\Models\AdmissionMessage;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionRegistration;
use App\Models\Site;
use App\Services\WhatsApp\FonnteGateway;
use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    Storage::fake('local');
    $this->pondok = onSite(SiteKey::Pondok);
    $this->period = AdmissionPeriod::factory()->for($this->pondok)->published()->create();
});

function gatewayOn(Site $site, array $settings = []): void
{
    $site->fillSettings([
        ...$site->allSettings(),
        'whatsapp.gateway_enabled' => true,
        'whatsapp.gateway_driver' => 'fonnte',
        'whatsapp.gateway_token' => Crypt::encryptString('rahasia-token'),
        ...$settings,
    ])->save();
}

function gatewayApplicant(AdmissionPeriod $period): AdmissionRegistration
{
    return AdmissionRegistration::query()->create([
        'site_id' => $period->site_id, 'admission_period_id' => $period->id,
        'full_name' => 'Ahmad Fauzi', 'gender' => 'L', 'birth_place' => 'Kendal', 'birth_date' => '2012-05-01',
        'previous_school' => 'SDN 1', 'parent_name' => 'Bapak Fauzi', 'parent_phone' => '081234567890', 'address' => 'Kendal',
    ]);
}

test('fonnte sends with the token and reports rejections and retryable errors', function () {
    Http::fake([
        'api.fonnte.com/*' => Http::sequence()
            ->push(['status' => true, 'detail' => 'success! message in queue'])
            ->push(['status' => false, 'reason' => 'invalid token'])
            ->push('', 500),
    ]);
    $gateway = new FonnteGateway('rahasia-token');

    expect($gateway->send('6281234567890', 'Halo')->ok)->toBeTrue();
    Http::assertSent(fn (Request $request) => $request->url() === FonnteGateway::ENDPOINT
        && $request->header('Authorization') === ['rahasia-token']
        && $request['target'] === '6281234567890'
        && $request['message'] === 'Halo');

    $rejected = $gateway->send('6281234567890', 'Halo');
    expect($rejected->ok)->toBeFalse()->and($rejected->retry)->toBeFalse()->and($rejected->error)->toBe('Fonnte: invalid token');

    expect($gateway->send('6281234567890', 'Halo')->retry)->toBeTrue();
});

test('a new registration is messaged automatically when the gateway is on', function () {
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);

    // Off: nothing is sent.
    $this->get('http://pondok.test/penerimaan/daftar');
    $this->travel(10)->seconds();
    $this->post('http://pondok.test/penerimaan/daftar', [
        'full_name' => 'Tanpa Gateway', 'gender' => 'L', 'birth_place' => 'Kendal', 'birth_date' => '2012-05-01',
        'previous_school' => 'SDN 1', 'program' => 'Tahfidz', 'parent_name' => 'Bapak A', 'parent_phone' => '081234567890',
        'address' => 'Kendal', 'agree' => '1',
    ])->assertRedirect();
    expect(AdmissionMessage::query()->count())->toBe(0);
    Http::assertNothingSent();

    gatewayOn($this->pondok);
    $this->get('http://pondok.test/penerimaan/daftar');
    $this->travel(10)->seconds();
    $this->post('http://pondok.test/penerimaan/daftar', [
        'full_name' => 'Siti Aisyah', 'gender' => 'P', 'birth_place' => 'Kendal', 'birth_date' => '2012-06-01',
        'previous_school' => 'SDN 2', 'program' => 'Tahfidz', 'parent_name' => 'Ibu Aisyah', 'parent_phone' => '081311112222',
        'address' => 'Kendal', 'agree' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $message = AdmissionMessage::query()->sole();
    expect($message->only(['event', 'channel', 'status', 'phone']))->toBe(['event' => WhatsAppEvent::Received, 'channel' => 'gateway', 'status' => 'sent', 'phone' => '6281311112222'])
        ->and($message->body)->toContain('*Siti Aisyah*');
    Http::assertSent(fn (Request $request) => $request['target'] === '6281311112222');
});

test('status changes and fix requests are sent automatically only for the ticked events', function () {
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
    gatewayOn($this->pondok, ['whatsapp.auto_rejected' => false]);
    $registration = gatewayApplicant($this->period);
    $requirement = $this->period->documentRequirements()->create(['name' => 'Kartu Keluarga', 'file_types' => ['pdf'], 'sort_order' => 0]);
    $document = new AdmissionDocument(['admission_registration_id' => $registration->id, 'admission_requirement_id' => $requirement->id]);
    $document->forceFill(['status' => DocumentStatus::Pending, 'uploaded_at' => now()])->save();
    $this->actingAs(cmsUser(Role::PondokAdmin));

    // Accepted is ticked: sent automatically, nothing prepared by hand.
    Livewire::test('pages::admin.admissions.registrations')
        ->call('show', $registration->id)
        ->set('newStatus', RegistrationStatus::Accepted->value)
        ->call('update')
        ->assertSet('waEvent', '');

    // A fix request is ticked too.
    Livewire::test('pages::admin.admissions.registrations')
        ->call('show', $registration->id)
        ->call('startFix', $document->id)
        ->set('fixNote', 'Mohon unggah ulang.')
        ->call('requestFix')
        ->assertSet('waEvent', '');

    // Not accepted is unticked: prepared for the admin instead.
    Livewire::test('pages::admin.admissions.registrations')
        ->call('show', $registration->id)
        ->set('newStatus', RegistrationStatus::Rejected->value)
        ->call('update')
        ->assertSet('waEvent', 'rejected');

    expect(AdmissionMessage::query()->where('channel', 'gateway')->orderBy('created_at')->get()->map(fn ($m) => [$m->event->value, $m->status])->all())
        ->toBe([['accepted', 'sent'], ['needs_fix', 'sent']])
        ->and(AdmissionMessage::query()->where('event', 'needs_fix')->sole()->body)->toContain('- Kartu Keluarga: Mohon unggah ulang.');
});

test('a rejected message is marked failed and can be sent by hand', function () {
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'target invalid'])]);
    gatewayOn($this->pondok);
    $registration = gatewayApplicant($this->period);

    $message = app(WhatsAppNotifier::class)->notify(WhatsAppEvent::Verified, $registration);
    expect($message->fresh()->only(['status', 'error']))->toBe(['status' => 'failed', 'error' => 'Fonnte: target invalid']);

    $this->actingAs(cmsUser(Role::PondokAdmin));
    Livewire::test('pages::admin.admissions.registrations')
        ->call('show', $registration->id)
        ->assertSee(['Not sent automatically', 'Fonnte: target invalid', 'Send by hand'])
        ->call('composeWhatsApp', 'verified')
        ->assertSet('waEvent', 'verified');
});

test('a switched-off gateway or a missing key sends nothing, and queued jobs then fail cleanly', function () {
    Http::fake();
    $registration = gatewayApplicant($this->period);
    $notifier = app(WhatsAppNotifier::class);

    expect($notifier->notify(WhatsAppEvent::Accepted, $registration))->toBeNull();

    gatewayOn($this->pondok, ['whatsapp.gateway_token' => 'not-encrypted']);
    expect($notifier->notify(WhatsAppEvent::Accepted, $registration->fresh()))->toBeNull()
        ->and($notifier->notify(WhatsAppEvent::Reminder, $registration->fresh()))->toBeNull();

    $queued = $registration->messages()->create(['event' => 'accepted', 'channel' => 'gateway', 'status' => 'queued', 'phone' => '6281234567890', 'body' => 'x']);
    (new SendAdmissionWhatsApp($queued->id))->handle($notifier);
    expect($queued->fresh()->status)->toBe('failed');
    Http::assertNothingSent();
});

test('the gateway key is stored encrypted, never shown, and can be tested', function () {
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
    $this->actingAs(cmsUser(Role::Superadmin));

    $component = Livewire::test('pages::admin.site-settings')
        ->set('tab', 'whatsapp')
        ->assertSee('Automatic sending')
        ->set('settings.whatsapp__gateway_enabled', true)
        ->call('save')
        ->assertHasErrors('settings.whatsapp__gateway_enabled')
        ->set('gatewayToken', 'rahasia-token')
        ->set('settings.whatsapp__auto_received', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('gatewayToken', '')
        ->assertSee('A key is saved')
        ->assertDontSee('rahasia-token');

    $stored = $this->pondok->fresh()->setting('whatsapp.gateway_token');
    expect($stored)->not->toBe('rahasia-token')
        ->and(Crypt::decryptString($stored))->toBe('rahasia-token')
        ->and($this->pondok->fresh()->setting('whatsapp.auto_received'))->toBeFalse();

    // Saving other settings keeps the key.
    $component->set('settings.identity__tagline', 'Baru')->call('save')->assertHasNoErrors();
    expect(Crypt::decryptString($this->pondok->fresh()->setting('whatsapp.gateway_token')))->toBe('rahasia-token');

    $component->set('testPhone', '12')->call('sendTestWhatsApp')->assertHasErrors('testPhone')
        ->set('testPhone', '0812 3456 7890')->call('sendTestWhatsApp')->assertHasNoErrors();
    Http::assertSent(fn (Request $request) => $request['target'] === '6281234567890' && $request->header('Authorization') === ['rahasia-token']);

    $component->call('removeGatewayToken');
    expect($this->pondok->fresh()->setting('whatsapp.gateway_token'))->toBe('')
        ->and($this->pondok->fresh()->setting('whatsapp.gateway_enabled'))->toBeFalse();
});
