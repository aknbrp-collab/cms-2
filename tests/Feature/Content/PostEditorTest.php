<?php

use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use App\Support\CurrentSite;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->pondok = onSite(SiteKey::Pondok);
    $this->admin = cmsUser(Role::PondokAdmin);
    $this->actingAs($this->admin);
});

test('a site admin creates a draft post on the current site', function () {
    Livewire::test('pages::admin.posts.edit')
        ->set('title', 'Wisuda Santri 2026')
        ->set('excerpt', 'Ringkasan')
        ->set('body', '<p>Isi berita</p><script>alert(1)</script>')
        ->set('seo.meta_title', 'Wisuda')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $post = Post::query()->sole();

    expect($post->site_id)->toBe($this->pondok->id)
        ->and($post->slug)->toBe('wisuda-santri-2026')
        ->and($post->author_id)->toBe($this->admin->id)
        ->and($post->status)->toBe(ContentStatus::Draft)
        ->and($post->body)->toBe('<p>Isi berita</p>')
        ->and($post->seo->meta_title)->toBe('Wisuda');
});

test('slugs are unique per site', function () {
    Post::factory()->for($this->pondok)->create(['slug' => 'pengumuman']);
    Post::factory()->for(Site::findByKey(SiteKey::Mahad))->create(['slug' => 'pengumuman-mahad']);

    Livewire::test('pages::admin.posts.edit')
        ->set('title', 'Pengumuman')
        ->call('save')
        ->assertHasErrors('slug');

    Livewire::test('pages::admin.posts.edit')
        ->set('title', 'Pengumuman Mahad')
        ->call('save')
        ->assertHasNoErrors();
});

test('categories of another site cannot be used', function () {
    $foreign = Category::factory()->for(Site::findByKey(SiteKey::Mahad))->create();

    Livewire::test('pages::admin.posts.edit')
        ->set('title', 'Berita')
        ->set('categoryId', $foreign->id)
        ->call('save')
        ->assertHasErrors('categoryId');
});

test('submitting from the editor saves first and sends for review', function () {
    $post = Post::factory()->for($this->pondok)->create(['author_id' => $this->admin->id]);

    Livewire::test('pages::admin.posts.edit', ['post' => $post])
        ->set('title', 'Judul baru')
        ->call('submitForReview')
        ->assertHasNoErrors();

    expect($post->fresh())
        ->title->toBe('Judul baru')
        ->status->toBe(ContentStatus::UnderReview);
});

test('locked posts cannot be saved by site admins', function () {
    $post = Post::factory()->for($this->pondok)->status(ContentStatus::Published)->create();

    Livewire::test('pages::admin.posts.edit', ['post' => $post])
        ->set('title', 'Hacked')
        ->call('save')
        ->assertForbidden();

    expect($post->fresh()->title)->not->toBe('Hacked');
});

test('an editor can request changes from the editor screen', function () {
    $post = Post::factory()->for($this->pondok)->status(ContentStatus::UnderReview)->create();
    $this->actingAs(cmsUser(Role::ManagingEditor));

    Livewire::test('pages::admin.posts.edit', ['post' => $post])
        ->call('reject')
        ->assertHasErrors('reviewNote')
        ->set('reviewNote', 'Tambahkan foto')
        ->call('reject')
        ->assertHasNoErrors();

    expect($post->fresh()->status)->toBe(ContentStatus::Draft);
});

test('the posts list only shows the current site', function () {
    $own = Post::factory()->for($this->pondok)->create();
    $other = Post::factory()->for(Site::findByKey(SiteKey::Mahad))->create();

    Livewire::test('pages::admin.posts.index')
        ->assertSee($own->title)
        ->assertDontSee($other->title);
});

test('site admins cannot create posts without a site', function () {
    $this->actingAs(cmsUser(Role::ManagingEditor));
    app(CurrentSite::class)->set(null);

    Livewire::test('pages::admin.posts.edit')->assertForbidden();
});

test('a new post stays on the site the editor was opened on', function () {
    $this->actingAs(cmsUser(Role::ManagingEditor));

    $component = Livewire::test('pages::admin.posts.edit');
    onSite(SiteKey::Mahad);

    $component->set('title', 'Tetap di Pondok')->call('save')->assertHasNoErrors();

    expect(Post::withoutSiteScope()->sole()->site_id)->toBe($this->pondok->id);
});
