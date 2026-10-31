<?php

use App\Enums\PublicationType;
use App\Enums\Role;
use App\Enums\SiakadArticleStatus;
use App\Enums\SiteKey;
use App\Models\Publication;
use App\Models\SiakadArticle;
use App\Models\Site;
use App\Siakad\ArticleInbox;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    $this->mahad = Site::findByKey(SiteKey::Mahad);
    $this->article = SiakadArticle::query()->create([
        'site_id' => $this->mahad->id, 'external_id' => 'ART-7', 'type' => PublicationType::Research,
        'title' => 'Fiqh Lingkungan', 'authors' => 'Ahmad', 'year' => 2026, 'payload' => [], 'received_at' => now(),
    ]);
});

test('ma\'had admins review but only editors approve', function () {
    $inbox = app(ArticleInbox::class);
    $admin = cmsUser(Role::MahadAdmin);

    $inbox->startReview($this->article, $admin);
    expect($this->article->fresh()->status)->toBe(SiakadArticleStatus::UnderReview);

    expect(fn () => $inbox->approve($this->article->fresh(), $admin))->toThrow(AuthorizationException::class)
        ->and(fn () => $inbox->startReview($this->article, cmsUser(Role::PondokAdmin)))->toThrow(AuthorizationException::class);
});

test('approval creates a published publication in the repository', function () {
    $this->withoutVite();
    app(ArticleInbox::class)->approve($this->article, cmsUser(Role::ManagingEditor));

    $publication = Publication::query()->withoutGlobalScopes()->sole();
    expect($publication->external_id)->toBe('ART-7')->and($publication->slug)->toBe('fiqh-lingkungan');

    $this->get('http://mahad.test/publikasi')->assertSee('Fiqh Lingkungan');
});

test('rejection needs a note in the admin screen', function () {
    onSite(SiteKey::Mahad);
    $this->actingAs(cmsUser(Role::ManagingEditor));

    Livewire::test('pages::admin.siakad-articles.show', ['article' => $this->article])
        ->call('reject')
        ->assertHasErrors('note')
        ->set('note', 'Belum sesuai')
        ->call('reject')
        ->assertHasNoErrors();

    expect($this->article->fresh())
        ->status->toBe(SiakadArticleStatus::Rejected)
        ->review_note->toBe('Belum sesuai');
});

test('the article inbox is visible to reviewers only', function () {
    $this->actingAs(cmsUser(Role::ManagingEditor))->get(route('admin.siakad-articles.index'))->assertOk()->assertSee('Fiqh Lingkungan');
    $this->actingAs(cmsUser(Role::SocialMediaManager))->get(route('admin.siakad-articles.index'))->assertForbidden();
});
