<?php

use App\Enums\ContentStatus;
use App\Enums\MenuLocation;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Publication;
use App\Models\Site;
use App\Support\Visibility;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    $this->pondok = Site::findByKey(SiteKey::Pondok);
});

test('hidden content disappears from the public site without changing its status', function (string $model, SiteKey $siteKey, string $host, string $prefix) {
    $item = $model::factory()->for(Site::findByKey($siteKey))->status(ContentStatus::Published)->create(['title' => 'Konten Uji Sembunyi']);
    $url = "http://{$host}/{$prefix}{$item->slug}";

    $this->get($url)->assertOk();

    Visibility::toggle($item);

    $this->get($url)->assertNotFound();
    $this->get("http://{$host}/sitemap.xml")->assertDontSee($item->slug);
    expect($item->fresh()->status)->toBe(ContentStatus::Published)
        ->and($model::query()->withoutGlobalScopes()->published()->count())->toBe(0);

    Visibility::toggle($item);

    $this->get($url)->assertOk();
})->with([
    'post' => [Post::class, SiteKey::Pondok, 'pondok.test', 'berita/'],
    'page' => [Page::class, SiteKey::Pondok, 'pondok.test', ''],
    'event' => [Event::class, SiteKey::Pondok, 'pondok.test', 'agenda/'],
    'gallery' => [Gallery::class, SiteKey::Pondok, 'pondok.test', 'galeri/'],
    'publication' => [Publication::class, SiteKey::Mahad, 'mahad.test', 'publikasi/'],
]);

test('hidden posts leave the lists and the home page', function () {
    $post = Post::factory()->for($this->pondok)->status(ContentStatus::Published)->create(['title' => 'Berita Disembunyikan', 'is_hidden' => true]);

    $this->get('http://pondok.test/berita')->assertOk()->assertDontSee('Berita Disembunyikan');
    $this->get('http://pondok.test/')->assertOk()->assertDontSee('Berita Disembunyikan');
    expect($post->isPublished())->toBeTrue();
});

test('a hidden category loses its page, sitemap entry and badges but its posts stay', function () {
    $category = Category::factory()->for($this->pondok)->create(['name' => 'Kategori Rahasia', 'show_on_home' => true, 'is_hidden' => true]);
    $post = Post::factory()->for($this->pondok)->status(ContentStatus::Published)->create(['title' => 'Berita Tetap Tampil', 'category_id' => $category->id]);

    $this->get("http://pondok.test/kategori/{$category->slug}")->assertNotFound();
    $this->get('http://pondok.test/sitemap.xml')->assertDontSee("/kategori/{$category->slug}");
    $this->get("http://pondok.test/berita/{$post->slug}")->assertOk()->assertSee('Berita Tetap Tampil')->assertDontSee('Kategori Rahasia');
    $this->get('http://pondok.test/berita')->assertSee('Berita Tetap Tampil')->assertDontSee('Kategori Rahasia');
});

test('hidden menu links, their sub-links and links to hidden targets are left out', function () {
    $menu = Menu::for($this->pondok, MenuLocation::Header);
    $hiddenParent = $menu->items()->create(['label' => 'Induk Tersembunyi', 'type' => 'url', 'url' => '/a', 'sort_order' => 0]);
    $hiddenParent->forceFill(['is_hidden' => true])->save();
    $menu->items()->create(['label' => 'Anak Tersembunyi', 'type' => 'url', 'url' => '/b', 'parent_id' => $hiddenParent->id, 'sort_order' => 0]);
    $visibleParent = $menu->items()->create(['label' => 'Induk Tampil', 'type' => 'url', 'url' => '/c', 'sort_order' => 1]);
    $menu->items()->create(['label' => 'Sub Tersembunyi', 'type' => 'url', 'url' => '/d', 'parent_id' => $visibleParent->id, 'sort_order' => 0])
        ->forceFill(['is_hidden' => true])->save();

    $hiddenPage = Page::factory()->for($this->pondok)->status(ContentStatus::Published)->create(['is_hidden' => true]);
    $draftPage = Page::factory()->for($this->pondok)->create();
    $hiddenCategory = Category::factory()->for($this->pondok)->create(['is_hidden' => true]);
    $menu->items()->create(['label' => 'Ke Halaman Tersembunyi', 'type' => 'page', 'linkable_type' => 'page', 'linkable_id' => $hiddenPage->id, 'sort_order' => 2]);
    $menu->items()->create(['label' => 'Ke Halaman Draft', 'type' => 'page', 'linkable_type' => 'page', 'linkable_id' => $draftPage->id, 'sort_order' => 3]);
    $menu->items()->create(['label' => 'Ke Kategori Tersembunyi', 'type' => 'category', 'linkable_type' => 'category', 'linkable_id' => $hiddenCategory->id, 'sort_order' => 4]);

    $this->get('http://pondok.test/')
        ->assertSee('Induk Tampil')
        ->assertDontSee('Induk Tersembunyi')
        ->assertDontSee('Anak Tersembunyi')
        ->assertDontSee('Sub Tersembunyi')
        ->assertDontSee('Ke Halaman Tersembunyi')
        ->assertDontSee('Ke Halaman Draft')
        ->assertDontSee('Ke Kategori Tersembunyi');
});

test('site admins can hide published content of their own site, and it is audited', function () {
    $post = Post::factory()->for($this->pondok)->status(ContentStatus::Published)->create();
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.posts.index')->call('toggleHidden', $post->id)->assertOk();

    expect($post->fresh()->is_hidden)->toBeTrue()
        ->and($post->fresh()->status)->toBe(ContentStatus::Published)
        ->and(AuditLog::query()->where('event', 'content.hidden')->where('auditable_id', $post->id)->exists())->toBeTrue();
});

test('hiding is refused for other sites and for roles without the module', function () {
    $post = Post::factory()->for($this->pondok)->status(ContentStatus::Published)->create();

    $this->actingAs(cmsUser(Role::MahadAdmin));
    onSite(SiteKey::Mahad);
    Livewire::test('pages::admin.posts.index')->call('toggleHidden', $post->id)->assertStatus(404);

    $this->actingAs(cmsUser(Role::SocialMediaManager));
    onSite(SiteKey::Pondok);
    Livewire::test('pages::admin.posts.index')->assertForbidden();

    expect($post->fresh()->is_hidden)->toBeFalse();
});

test('the editor sidebar switch hides and shows', function () {
    $page = Page::factory()->for($this->pondok)->status(ContentStatus::Published)->create();
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    $component = Livewire::test('pages::admin.pages.edit', ['page' => $page])
        ->assertSee('Show on the website')
        ->call('toggleHidden');
    expect($page->fresh()->is_hidden)->toBeTrue();

    $component->call('toggleHidden');
    expect($page->fresh()->is_hidden)->toBeFalse();
});

test('lists filter by visibility and mark hidden items', function () {
    Post::factory()->for($this->pondok)->create(['title' => 'Tampil Biasa']);
    Post::factory()->for($this->pondok)->create(['title' => 'Sedang Disembunyikan', 'is_hidden' => true]);
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.posts.index')
        ->assertSee('Tampil Biasa')
        ->assertSee('Sedang Disembunyikan')
        ->set('visibility', 'hidden')
        ->assertDontSee('Tampil Biasa')
        ->assertSee('Sedang Disembunyikan')
        ->set('visibility', 'visible')
        ->assertSee('Tampil Biasa')
        ->assertDontSee('Sedang Disembunyikan');
});

test('categories and menu links are hidden by users who can edit them', function () {
    $category = Category::factory()->for($this->pondok)->create();
    $item = Menu::for($this->pondok, MenuLocation::Header)->items()->create(['label' => 'Tautan', 'type' => 'url', 'url' => '/x', 'sort_order' => 0]);
    onSite(SiteKey::Pondok);

    $this->actingAs(cmsUser(Role::PondokAdmin));
    Livewire::test('pages::admin.categories')->call('toggleHidden', $category->id)->assertOk();
    Livewire::test('pages::admin.menus')->call('toggleHidden', $item->id)->assertOk();

    expect($category->fresh()->is_hidden)->toBeTrue()->and($item->fresh()->is_hidden)->toBeTrue();

    $this->actingAs(cmsUser(Role::SocialMediaManager));
    Livewire::test('pages::admin.categories')->call('toggleHidden', $category->id)->assertForbidden();
    expect($category->fresh()->is_hidden)->toBeTrue();
});
