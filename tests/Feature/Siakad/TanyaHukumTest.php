<?php

use App\Enums\LegalCategory;
use App\Enums\LegalQuestionStatus;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Jobs\ForwardLegalQuestion;
use App\Models\IntegrationLog;
use App\Models\LegalQuestion;
use App\Models\Site;
use App\Notifications\LegalQuestionUpdated;
use App\Siakad\HttpSiakadClient;
use App\Siakad\SiakadClient;
use App\Siakad\SiakadException;
use App\Siakad\TanyaHukum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    $this->mahad = Site::findByKey(SiteKey::Mahad);
    $this->moderator = cmsUser(Role::TanyaHukumAdmin);
});

function newQuestion(Site $site, array $attributes = []): LegalQuestion
{
    return LegalQuestion::query()->create([
        'site_id' => $site->id,
        'category' => LegalCategory::Muamalah,
        'asker_name' => 'Ahmad',
        'asker_email' => 'ahmad@example.test',
        'question' => 'Bagaimana hukum jual beli dengan sistem pre-order yang barangnya belum ada?',
        ...$attributes,
    ]);
}

test('a visitor asks a question and can track its status', function () {
    Notification::fake();
    $this->get('http://mahad.test/tanya-hukum/tanya')->assertOk();
    $this->travel(10)->seconds();

    $response = $this->post('http://mahad.test/tanya-hukum/tanya', [
        'category' => 'ibadah',
        'question' => 'Bolehkah menjamak shalat ketika perjalanan kurang dari dua hari?',
        'is_anonymous' => '1',
        'agree' => '1',
    ]);

    $question = LegalQuestion::query()->sole();
    $response->assertRedirect(route('site.legal-questions.status', ['kode' => $question->tracking_code]));

    expect($question->is_anonymous)->toBeTrue()->and($question->status)->toBe(LegalQuestionStatus::Submitted);
    Notification::assertSentTo($this->moderator, LegalQuestionUpdated::class);

    $this->get("http://mahad.test/tanya-hukum/status?kode={$question->tracking_code}")
        ->assertOk()
        ->assertSee('sedang ditinjau')
        ->assertDontSee('menjamak shalat');
});

test('spam and incomplete questions are refused', function () {
    $this->get('http://mahad.test/tanya-hukum/tanya');
    $this->post('http://mahad.test/tanya-hukum/tanya', ['category' => 'ibadah', 'question' => str_repeat('a ', 30), 'is_anonymous' => '1', 'agree' => '1'])
        ->assertSessionHasErrors('question');

    $this->travel(10)->seconds();
    $this->post('http://mahad.test/tanya-hukum/tanya', ['category' => 'ibadah', 'question' => 'pendek', 'agree' => '1', 'website' => 'x'])
        ->assertSessionHasErrors(['question', 'asker_name', 'website']);

    expect(LegalQuestion::query()->count())->toBe(0);
});

test('tanya hukum is only on the ma\'had site', function () {
    $this->get('http://pondok.test/tanya-hukum')->assertNotFound();
    $this->get('http://pondok.test/tanya-hukum/tanya')->assertNotFound();
});

test('a moderator forwards a question and the job marks it forwarded', function () {
    Queue::fake();
    $question = newQuestion($this->mahad);

    app(TanyaHukum::class)->forward($question, $this->moderator, 'Mohon rujukan Syafi\'iyah');

    expect($question->fresh()->status)->toBe(LegalQuestionStatus::Forwarding);
    Queue::assertPushed(ForwardLegalQuestion::class);

    (new ForwardLegalQuestion($question->fresh()))->handle(app(SiakadClient::class));

    $question->refresh();
    expect($question->status)->toBe(LegalQuestionStatus::Forwarded)
        ->and($question->external_id)->toStartWith('SIAKAD-Q-')
        ->and(IntegrationLog::query()->where('action', 'legal_questions.forward')->where('status', 'success')->exists())->toBeTrue();
});

test('the http client sends a signed request without the asker email', function () {
    config(['siakad.driver' => 'http', 'siakad.base_url' => 'https://siakad.test/api/cms', 'siakad.api_key' => 'key', 'siakad.webhook_secret' => 'secret']);
    Http::fake(['siakad.test/*' => Http::response(['id' => 'SQ-99'], 201)]);
    $question = newQuestion($this->mahad);
    $question->forceFill(['status' => LegalQuestionStatus::Forwarding])->save();

    (new ForwardLegalQuestion($question))->handle(app(SiakadClient::class));

    expect($question->fresh()->external_id)->toBe('SQ-99');
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer key')
        && $request->hasHeader('X-Siakad-Signature')
        && $request->hasHeader('Idempotency-Key', $question->id)
        && $request['cms_question_id'] === $question->id
        && ! str_contains($request->body(), 'ahmad@example.test'));
});

test('failed forwarding is logged and the question returns to the moderator', function () {
    Http::fake(['siakad.test/*' => Http::response(['message' => 'down'], 503)]);
    config(['siakad.base_url' => 'https://siakad.test/api/cms']);
    $question = newQuestion($this->mahad);
    $question->forceFill(['status' => LegalQuestionStatus::Forwarding])->save();
    $job = new ForwardLegalQuestion($question);

    expect(fn () => $job->handle(new HttpSiakadClient))->toThrow(SiakadException::class);
    expect(IntegrationLog::query()->where('status', 'failed')->where('http_status', 503)->exists())->toBeTrue();

    $job->failed(new SiakadException('down'));
    expect($question->fresh()->status)->toBe(LegalQuestionStatus::Submitted);
});

test('a moderator publishes an answered question without personal data', function () {
    $question = newQuestion($this->mahad, ['is_anonymous' => true]);
    $question->forceFill(['status' => LegalQuestionStatus::Answered, 'answer' => '<p>Boleh dengan syarat.</p>', 'answered_by' => 'Dewan Asatidz'])->save();

    app(TanyaHukum::class)->publish($question, $this->moderator, 'Hukum Jual Beli Pre-Order', 'Bagaimana hukum jual beli pre-order?');

    $this->get('http://mahad.test/tanya-hukum')->assertOk()->assertSee('Hukum Jual Beli Pre-Order');
    $this->get('http://mahad.test/tanya-hukum/hukum-jual-beli-pre-order')
        ->assertOk()
        ->assertSee('Boleh dengan syarat.')
        ->assertSee('Hamba Allah')
        ->assertDontSee('Ahmad')
        ->assertDontSee('ahmad@example.test');
    $this->get('http://mahad.test/sitemap.xml')->assertSee('tanya-hukum/hukum-jual-beli-pre-order', false);
});

test('only tanya hukum moderators can act on questions', function () {
    $question = newQuestion($this->mahad);

    expect(fn () => app(TanyaHukum::class)->forward($question, cmsUser(Role::MahadAdmin)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TanyaHukum::class)->reject($question, cmsUser(Role::PondokAdmin), 'x'))->toThrow(AuthorizationException::class);
});

test('the admin moderation screens work', function () {
    Queue::fake();
    $question = newQuestion($this->mahad);
    onSite(SiteKey::Mahad);
    $this->actingAs($this->moderator);

    $this->get(route('admin.legal-questions.index'))->assertOk()->assertSee($question->tracking_code);

    Livewire::test('pages::admin.legal-questions.show', ['question' => $question])
        ->assertSee('ahmad@example.test')
        ->call('forward')
        ->assertHasNoErrors();

    expect($question->fresh()->status)->toBe(LegalQuestionStatus::Forwarding);
    $this->get(route('admin.integration-logs'))->assertOk();
});
