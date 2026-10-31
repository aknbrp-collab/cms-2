<?php

use App\Enums\ContentStatus;
use App\Enums\MenuLocation;
use App\Enums\PageTemplate;
use App\Enums\PostType;
use App\Enums\SiteKey;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

beforeEach(function () {
    seedCms();
    $this->withoutVite();

    $this->pondok = Site::findByKey(SiteKey::Pondok);
    $this->mahad = Site::findByKey(SiteKey::Mahad);
});

test('each site renders its own theme', function () {
    $this->get('http://pondok.test/')->assertOk()->assertSee(e($this->pondok->name), false)->assertDontSee('السَّلَامُ', false);
    $this->get('http://mahad.test/')->assertOk()->assertSee('السَّلَامُ', false);
});

test('only published posts of the current site are shown', function () {
    $published = Post::factory()->for($this->pondok)->status(ContentStatus::Published)->create(['title' => 'Berita Terbit']);
    $draft = Post::factory()->for($this->pondok)->create(['title' => 'Masih Draft']);
    $scheduled = Post::factory()->for($this->pondok)->create(['title' => 'Dijadwalkan', 'status' => ContentStatus::Published, 'published_at' => now()->addDay()]);
    $otherSite = Post::factory()->for($this->mahad)->status(ContentStatus::Published)->create(['title' => 'Berita Mahad']);

    $this->get('http://pondok.test/berita')
        ->assertOk()
        ->assertSee('Berita Terbit')
        ->assertDontSee('Masih Draft')
        ->assertDontSee('Dijadwalkan')
        ->assertDontSee('Berita Mahad');

    $this->get("http://pondok.test/berita/{$published->slug}")->assertOk()->assertSee('Berita Terbit');
    $this->get("http://pondok.test/berita/{$draft->slug}")->assertNotFound();
    $this->get("http://pondok.test/berita/{$scheduled->slug}")->assertNotFound();
    $this->get("http://pondok.test/berita/{$otherSite->slug}")->assertNotFound();
});

test('announcements have their own list', function () {
    Post::factory()->for($this->pondok)->status(ContentStatus::Published)->create(['title' => 'Libur Semester', 'type' => PostType::Announcement]);
    Post::factory()->for($this->pondok)->status(ContentStatus::Published)->create(['title' => 'Berita Biasa']);

    $this->get('http://pondok.test/pengumuman')->assertOk()->assertSee('Libur Semester')->assertDontSee('Berita Biasa');
    $this->get('http://pondok.test/berita')->assertOk()->assertSee('Berita Biasa')->assertDontSee('Libur Semester');
});

test('category pages list their posts', function () {
    $category = Category::factory()->for($this->mahad)->create(['name' => 'Bahtsul Masail', 'slug' => 'bahtsul-masail']);
    Post::factory()->for($this->mahad)->status(ContentStatus::Published)->create(['title' => 'Hukum Akad Digital', 'category_id' => $category->id]);

    $this->get('http://mahad.test/kategori/bahtsul-masail')->assertOk()->assertSee('Hukum Akad Digital');
    $this->get('http://pondok.test/kategori/bahtsul-masail')->assertNotFound();
});

test('published pages render their blocks and seo', function () {
    $page = Page::factory()->for($this->pondok)->status(ContentStatus::Published)->create([
        'title' => 'Sejarah',
        'slug' => 'sejarah',
        'template' => PageTemplate::Contact,
        'blocks' => [['id' => 'a', 'type' => 'rich_text', 'data' => ['html' => '<p>Didirikan 1990</p>']]],
    ]);
    $page->saveSeo(['meta_title' => 'Sejarah Pondok', 'noindex' => true]);
    $this->pondok->fillSettings([...$this->pondok->allSettings(), 'contact.phone' => '0294-1234'])->save();

    $this->get('http://pondok.test/sejarah')
        ->assertOk()
        ->assertSee('<p>Didirikan 1990</p>', false)
        ->assertSee('<title>Sejarah Pondok | '.e($this->pondok->name).'</title>', false)
        ->assertSee('name="robots" content="noindex, nofollow"', false)
        ->assertSee('0294-1234');

    Page::factory()->for($this->pondok)->create(['slug' => 'rahasia']);
    $this->get('http://pondok.test/rahasia')->assertNotFound();
});

test('menus come from the cms', function () {
    $menu = Menu::for($this->pondok, MenuLocation::Header);
    $menu->items()->create(['label' => 'Galeri Kegiatan', 'type' => 'url', 'url' => '/galeri', 'sort_order' => 0]);

    $this->get('http://pondok.test/')->assertSee('Galeri Kegiatan')->assertSee('href="/galeri"', false);
});

test('brand colours and analytics come from site settings', function () {
    $this->mahad->fillSettings([
        ...$this->mahad->allSettings(),
        'theme.primary' => '#112233',
        'analytics.ga_measurement_id' => 'G-TEST123',
    ])->save();

    $this->get('http://mahad.test/')
        ->assertSee('--color-primary: #112233', false)
        ->assertSee('googletagmanager.com/gtag/js?id=G-TEST123', false);
});

test('the mahad top bar shows hijri date and prayer times when enabled', function () {
    $this->mahad->fillSettings([
        ...$this->mahad->allSettings(),
        'prayer.enabled' => true,
        'prayer.latitude' => -6.2,
        'prayer.longitude' => 106.8,
    ])->save();

    $this->get('http://mahad.test/')->assertSee(' H /', false)->assertSee('prayerCountdown', false);
});

test('sitemap and robots are generated per site', function () {
    Post::factory()->for($this->pondok)->status(ContentStatus::Published)->create(['slug' => 'wisuda']);
    Post::factory()->for($this->mahad)->status(ContentStatus::Published)->create(['slug' => 'simposium']);

    $this->get('http://pondok.test/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('http://pondok.test/berita/wisuda', false)
        ->assertDontSee('simposium');

    $this->get('http://mahad.test/robots.txt')->assertOk()->assertSee('Sitemap: http://mahad.test/sitemap.xml', false);
});

test('unknown pages show the themed 404', function () {
    $this->get('http://pondok.test/tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan');
});
