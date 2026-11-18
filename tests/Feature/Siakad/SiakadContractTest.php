<?php

use App\Enums\LegalCategory;
use App\Enums\LegalQuestionStatus;
use App\Enums\Role;
use App\Enums\SiakadArticleStatus;
use App\Enums\SiteKey;
use App\Jobs\ForwardLegalQuestion;
use App\Models\LegalQuestion;
use App\Models\SiakadArticle;
use App\Models\Site;
use App\Notifications\LegalQuestionUpdated;
use App\Notifications\SiakadArticleReceived;
use App\Siakad\ArticleInbox;
use App\Siakad\SiakadException;
use App\Siakad\TanyaHukum;
use App\Support\Visibility;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * Behaviour added in version 1.1 of docs/siakad-api-spec.md.
 * Uses siakadCall() and articlePayload() from tests/Pest.php.
 */

beforeEach(function () {
    seedCms();
    config(['siakad.webhook_secret' => 'test-secret', 'siakad.site' => 'mahad']);
    $this->mahad = Site::findByKey(SiteKey::Mahad);
});

function publishedQuestion(Site $site): LegalQuestion
{
    $question = LegalQuestion::query()->create(['site_id' => $site->id, 'category' => LegalCategory::Ibadah, 'question' => str_repeat('Pertanyaan ', 5)]);
    $question->forceFill([
        'status' => LegalQuestionStatus::Published, 'external_id' => 'SQ-7', 'slug' => 'pertanyaan-uji', 'title' => 'Pertanyaan uji',
        'public_question' => 'Pertanyaan uji', 'answer' => '<p>Jawaban yang sudah disunting moderator.</p>',
        'answered_by' => 'Dewan Asatidz', 'answered_at' => now()->subDay(), 'published_at' => now()->subDay(),
    ])->save();

    return $question;
}

test('siakad reads the review status and public url of an article', function () {
    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload())->assertCreated();

    siakadCall('GET', route('webhooks.siakad.articles.show', 'ART-1'))
        ->assertOk()
        ->assertJson(['id' => 'ART-1', 'review_status' => 'draft', 'public_url' => null, 'changes_pending_review' => false]);

    $publication = app(ArticleInbox::class)->approve(SiakadArticle::query()->sole(), cmsUser(Role::ManagingEditor));

    siakadCall('GET', route('webhooks.siakad.articles.show', 'ART-1'))
        ->assertOk()
        ->assertJson(['review_status' => 'approved', 'public_url' => 'http://mahad.test/publikasi/'.$publication->slug]);

    Visibility::toggle($publication);
    siakadCall('GET', route('webhooks.siakad.articles.show', 'ART-1'))->assertJson(['review_status' => 'approved', 'public_url' => null]);
    Visibility::toggle($publication);

    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload(['title' => 'Revisi']))->assertOk();
    siakadCall('GET', route('webhooks.siakad.articles.show', 'ART-1'))
        ->assertJson(['review_status' => 'under_review', 'changes_pending_review' => true, 'public_url' => 'http://mahad.test/publikasi/'.$publication->slug]);

    siakadCall('GET', route('webhooks.siakad.articles.show', 'ART-404'))->assertNotFound()->assertJson(['message' => 'Article not found.']);
    $this->getJson(route('webhooks.siakad.articles.show', 'ART-1'))->assertUnauthorized();
});

test('a rejected article sent again goes back to review with a note for siakad', function () {
    Notification::fake();
    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload())->assertCreated();
    $article = SiakadArticle::query()->sole();
    $editor = cmsUser(Role::ManagingEditor);
    app(ArticleInbox::class)->reject($article, $editor, 'Abstrak terlalu pendek.');

    siakadCall('GET', route('webhooks.siakad.articles.show', 'ART-1'))->assertJson(['review_status' => 'rejected', 'review_note' => 'Abstrak terlalu pendek.']);

    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload(['abstract' => 'Abstrak yang lebih lengkap.']))
        ->assertOk()
        ->assertJson(['review_status' => 'under_review']);

    expect($article->fresh())->status->toBe(SiakadArticleStatus::UnderReview)->abstract->toBe('Abstrak yang lebih lengkap.');
    Notification::assertSentTo($editor, SiakadArticleReceived::class, fn (SiakadArticleReceived $notification) => $notification->resubmitted);
    siakadCall('GET', route('webhooks.siakad.articles.show', 'ART-1'))->assertJson(['review_note' => null]);
});

test('an answer after publication waits for the moderator', function () {
    Notification::fake();
    $moderator = cmsUser(Role::TanyaHukumAdmin);
    $question = publishedQuestion($this->mahad);

    siakadCall('POST', route('webhooks.siakad.answers'), [
        'siakad_question_id' => 'SQ-7', 'answer_html' => '<p>Jawaban yang dikoreksi.</p>', 'answered_by' => 'Dewan Asatidz', 'references' => 'Fathul Qarib',
    ])->assertOk()->assertJson(['question_status' => 'published', 'correction_pending' => true]);

    $question->refresh();
    expect($question->answer)->toBe('<p>Jawaban yang sudah disunting moderator.</p>')
        ->and($question->pending_answer)->toBe('<p>Jawaban yang dikoreksi.</p>')
        ->and($question->pending_answer_references)->toBe('Fathul Qarib');
    Notification::assertSentTo($moderator, LegalQuestionUpdated::class, fn (LegalQuestionUpdated $notification) => $notification->event === 'corrected');

    $this->get('http://mahad.test/tanya-hukum/pertanyaan-uji')->assertOk()->assertSee('Jawaban yang sudah disunting moderator.')->assertDontSee('Jawaban yang dikoreksi.');
});

test('moderators apply or dismiss a correction', function () {
    $question = publishedQuestion($this->mahad);
    $question->forceFill(['pending_answer' => '<p>Koreksi.</p>', 'pending_answered_by' => 'Dewan Asatidz', 'pending_answer_at' => now()])->save();
    onSite(SiteKey::Mahad);
    $this->actingAs(cmsUser(Role::TanyaHukumAdmin));

    Livewire::test('pages::admin.legal-questions.show', ['question' => $question])
        ->assertSee('Corrected answer from SIAKAD')
        ->call('applyCorrection')
        ->assertHasNoErrors();

    expect($question->fresh())->answer->toBe('<p>Koreksi.</p>')->pending_answer->toBeNull();

    $question->forceFill(['pending_answer' => '<p>Koreksi kedua.</p>', 'pending_answered_by' => 'Dewan Asatidz', 'pending_answer_at' => now()])->save();
    Livewire::test('pages::admin.legal-questions.show', ['question' => $question])->call('dismissCorrection');

    expect($question->fresh())->answer->toBe('<p>Koreksi.</p>')->pending_answer->toBeNull();
    expect(fn () => app(TanyaHukum::class)->dismissCorrection($question->fresh(), cmsUser(Role::TanyaHukumAdmin)))->toThrow(ValidationException::class);
});

test('a refused forward is handed back to the moderator at once', function (int $status) {
    config(['siakad.driver' => 'http', 'siakad.base_url' => 'https://siakad.test/api/cms', 'siakad.api_key' => 'key']);
    Http::fake(['siakad.test/*' => Http::response(['message' => 'refused'], $status)]);
    $question = LegalQuestion::query()->create(['site_id' => $this->mahad->id, 'category' => LegalCategory::Ibadah, 'question' => str_repeat('Pertanyaan ', 5)]);
    $question->forceFill(['status' => LegalQuestionStatus::Forwarding])->save();

    ForwardLegalQuestion::dispatchSync($question);

    expect($question->fresh()->status)->toBe(LegalQuestionStatus::Submitted);
    Http::assertSentCount(1);
})->with([401, 403, 422]);

test('temporary failures are retried', function (int $status) {
    expect((new SiakadException('x', $status))->isPermanent())->toBeFalse();
})->with([408, 429, 500, 503]);

test('connection errors and empty successes are retried too', function () {
    expect((new SiakadException('timeout'))->isPermanent())->toBeFalse()
        ->and((new SiakadException('no id', 201))->isPermanent())->toBeFalse()
        ->and((new SiakadException('refused', 422))->isPermanent())->toBeTrue();
});

test('the simulator reads the status', function () {
    $this->artisan('siakad:simulate', ['action' => 'article', '--id' => 'ART-ST'])->assertSuccessful();
    $this->artisan('siakad:simulate', ['action' => 'status', '--id' => 'ART-ST'])->expectsOutputToContain('"review_status":"draft"')->assertSuccessful();
});
