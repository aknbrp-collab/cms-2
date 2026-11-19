<?php

use App\Enums\ContentStatus;
use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Enums\SiteKey;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Post;
use App\Models\Site;
use Database\Seeders\KolomAlumniSeeder;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    $this->pondok = Site::findByKey(SiteKey::Pondok);
});

test('the seeder adds the Kolom Alumni category at the end of the Berita dropdown, once', function () {
    $menu = Menu::for($this->pondok, MenuLocation::Header);
    $berita = $menu->items()->create(['label' => 'Berita', 'type' => 'url', 'url' => '#', 'sort_order' => 0]);
    $menu->items()->create(['label' => 'Berita', 'parent_id' => $berita->id, 'type' => 'url', 'url' => '/berita', 'sort_order' => 0]);
    $menu->items()->create(['label' => 'Pengumuman', 'parent_id' => $berita->id, 'type' => 'url', 'url' => '/pengumuman', 'sort_order' => 1]);

    (new KolomAlumniSeeder)->run();
    (new KolomAlumniSeeder)->run();

    $category = Category::withoutGlobalScopes()->where('site_id', $this->pondok->id)->sole();
    expect($category->only(['name', 'slug', 'show_on_home']))->toBe(['name' => 'Kolom Alumni', 'slug' => 'kolom-alumni', 'show_on_home' => false]);

    $children = $berita->children()->get();
    $link = $children->last();
    expect($children->pluck('label')->all())->toBe(['Berita', 'Pengumuman', 'Kolom Alumni'])
        ->and($link->type)->toBe(MenuItemType::Category)
        ->and($link->href())->toBe('/kategori/kolom-alumni');

    $post = Post::factory()->for($this->pondok)->status(ContentStatus::Published)->create(['title' => 'Kenangan Mondok 2010', 'category_id' => $category->id]);
    $this->get('http://pondok.test/kategori/kolom-alumni')->assertOk()->assertSee('Kolom Alumni')->assertSee($post->title);
    $this->get('http://pondok.test/')->assertSee('href="/kategori/kolom-alumni"', false);
});

test('without a Berita dropdown only the category is added', function () {
    (new KolomAlumniSeeder)->run();

    expect(Category::withoutGlobalScopes()->where('slug', 'kolom-alumni')->exists())->toBeTrue()
        ->and(Menu::for($this->pondok, MenuLocation::Header)->items()->count())->toBe(0);
});
