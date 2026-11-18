<?php

use App\Enums\ContentStatus;
use App\Enums\LegalCategory;
use App\Enums\LegalQuestionStatus;
use App\Enums\Role;
use App\Enums\SiakadArticleStatus;
use App\Enums\SiteKey;
use App\Models\IntegrationLog;
use App\Models\LegalQuestion;
use App\Models\SiakadArticle;
use App\Models\Site;
use App\Siakad\ArticleInbox;

beforeEach(function () {
    seedCms();
    config(['siakad.webhook_secret' => 'test-secret', 'siakad.site' => 'mahad']);
    $this->mahad = Site::findByKey(SiteKey::Mahad);
});

test('a signed article is stored as a draft for review', function () {
    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload())
        ->assertCreated()
        ->assertJson(['status' => 'received', 'review_status' => 'draft']);

    $article = SiakadArticle::query()->sole();

    expect($article->site_id)->toBe($this->mahad->id)
        ->and($article->authors)->toBe('Dr. Mahbub Ridwan')
        ->and($article->body)->toBe('<p>Isi</p>')
        ->and(IntegrationLog::query()->where('action', 'articles.received')->where('status', 'success')->exists())->toBeTrue();
});

test('resending an article updates it instead of duplicating', function () {
    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload())->assertCreated();
    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload(['title' => 'Judul Baru']))->assertOk();

    expect(SiakadArticle::query()->sole()->title)->toBe('Judul Baru');
});

test('unsigned, wrongly signed and expired requests are refused and logged', function () {
    $this->postJson(route('webhooks.siakad.articles'), articlePayload())->assertUnauthorized();
    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload(), secret: 'wrong')->assertUnauthorized();
    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload(), timestamp: now()->subMinutes(10)->getTimestamp())->assertUnauthorized();

    expect(SiakadArticle::query()->count())->toBe(0)
        ->and(IntegrationLog::query()->where('status', IntegrationLog::REJECTED)->count())->toBe(3);
});

test('webhooks are refused when no secret is configured', function () {
    config(['siakad.webhook_secret' => null]);

    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload(), secret: '')->assertUnauthorized();
});

test('invalid payloads are rejected with validation errors', function () {
    siakadCall('POST', route('webhooks.siakad.articles'), ['id' => 'X', 'type' => 'poem'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'authors', 'year', 'type']);

    expect(IntegrationLog::query()->where('status', 'rejected')->where('http_status', 422)->exists())->toBeTrue();
});

test('approval publishes the article and withdrawal takes it offline', function () {
    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload())->assertCreated();
    $article = SiakadArticle::query()->sole();

    $publication = app(ArticleInbox::class)->approve($article, cmsUser(Role::ManagingEditor));

    expect($publication->status)->toBe(ContentStatus::Published)
        ->and($publication->external_source)->toBe('siakad')
        ->and($article->fresh()->status)->toBe(SiakadArticleStatus::Approved);

    siakadCall('DELETE', route('webhooks.siakad.articles.withdraw', 'ART-1'))->assertOk()->assertJson(['status' => 'withdrawn']);

    expect($publication->fresh()->status)->toBe(ContentStatus::Archived)
        ->and($article->fresh()->status)->toBe(SiakadArticleStatus::Withdrawn);

    siakadCall('DELETE', route('webhooks.siakad.articles.withdraw', 'ART-404'))->assertNotFound();
});

test('changes after approval go back to review', function () {
    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload())->assertCreated();
    app(ArticleInbox::class)->approve(SiakadArticle::query()->sole(), cmsUser(Role::ManagingEditor));

    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload(['title' => 'Revisi']))->assertOk();

    $article = SiakadArticle::query()->sole();
    expect($article->status)->toBe(SiakadArticleStatus::UnderReview)
        ->and($article->updated_after_approval)->toBeTrue()
        ->and($article->publication->title)->toBe('Metodologi Qiyas');
});

test('an answer is stored for a forwarded question', function () {
    $question = LegalQuestion::query()->create(['site_id' => $this->mahad->id, 'category' => LegalCategory::Ibadah, 'question' => str_repeat('Pertanyaan ', 5)]);
    $question->forceFill(['status' => LegalQuestionStatus::Forwarded, 'external_id' => 'SQ-1'])->save();

    siakadCall('POST', route('webhooks.siakad.answers'), [
        'siakad_question_id' => 'SQ-1',
        'answer_html' => '<p>Boleh.</p><img src=x onerror=alert(1)>',
        'answered_by' => 'Dewan Asatidz',
    ])->assertOk()->assertJson(['question_status' => 'answered']);

    $question->refresh();
    expect($question->status)->toBe(LegalQuestionStatus::Answered)
        ->and($question->answer)->toContain('<p>Boleh.</p>')->not->toContain('onerror');
});

test('answers for unknown or non-forwarded questions are refused', function () {
    $question = LegalQuestion::query()->create(['site_id' => $this->mahad->id, 'category' => LegalCategory::Ibadah, 'question' => str_repeat('Pertanyaan ', 5)]);

    siakadCall('POST', route('webhooks.siakad.answers'), ['question_id' => $question->id, 'answer_html' => 'x', 'answered_by' => 'y'])->assertStatus(409);
    siakadCall('POST', route('webhooks.siakad.answers'), ['siakad_question_id' => 'NOPE', 'answer_html' => 'x', 'answered_by' => 'y'])->assertNotFound();
});

test('the simulator sends valid signed webhooks', function () {
    $this->artisan('siakad:simulate', ['action' => 'article', '--id' => 'ART-SIM'])->assertSuccessful();

    expect(SiakadArticle::query()->where('external_id', 'ART-SIM')->exists())->toBeTrue();
});
