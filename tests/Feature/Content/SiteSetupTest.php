<?php

use App\Enums\MenuLocation;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    Storage::fake('public');
    $this->site = onSite(SiteKey::Pondok);
});

test('a superadmin saves site settings and branding', function () {
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.site-settings')
        ->set('settings.contact__phone', '0294-452-473')
        ->set('settings.theme__primary', '#005D42')
        ->set('settings.social__instagram', 'https://instagram.com/darulamanah')
        ->set('branding.logo', UploadedFile::fake()->image('logo.png'))
        ->call('save')
        ->assertHasNoErrors();

    $site = $this->site->fresh();

    expect($site->setting('contact.phone'))->toBe('0294-452-473')
        ->and($site->setting('theme.primary'))->toBe('#005D42')
        ->and($site->setting('theme.secondary'))->toBe('#B45309')
        ->and($site->setting('social.instagram'))->toBe('https://instagram.com/darulamanah')
        ->and($site->brandingUrl('logo'))->not->toBe('');
});

test('site settings are validated', function () {
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.site-settings')
        ->set('settings.theme__primary', 'green')
        ->set('settings.contact__map_embed_url', 'https://evil.example.com')
        ->set('settings.prayer__enabled', true)
        ->call('save')
        ->assertHasErrors(['settings.theme__primary', 'settings.contact__map_embed_url', 'settings.prayer__latitude']);
});

test('only superadmins open site settings', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin))->get(route('admin.site-settings'))->assertForbidden();
});

test('categories are unique per site', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin));
    Category::factory()->for($this->site)->create(['slug' => 'keislaman']);

    Livewire::test('pages::admin.categories')
        ->set('name', 'Keislaman')
        ->call('save')
        ->assertHasErrors('slug')
        ->set('slug', 'prestasi-santri')
        ->set('showOnHome', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Category::query()->where('slug', 'prestasi-santri')->sole()->show_on_home)->toBeTrue();
});

test('menus hold links, pages and nested items in order', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin));
    $page = Page::factory()->for($this->site)->create(['slug' => 'sejarah']);

    $component = Livewire::test('pages::admin.menus')
        ->set('label', 'Profil')->set('type', 'url')->set('url', '#')->call('save')
        ->assertHasNoErrors();

    $menu = Menu::for($this->site, MenuLocation::Header);
    $profil = MenuItem::query()->where('label', 'Profil')->sole();

    $component
        ->set('label', 'Sejarah')->set('type', 'page')->set('linkableId', $page->id)->set('parentId', $profil->id)->call('save')
        ->set('label', 'Kontak')->set('type', 'url')->set('url', '/kontak')->call('save')
        ->assertHasNoErrors()
        ->call('move', MenuItem::query()->where('label', 'Kontak')->value('id'), -1);

    $roots = $menu->rootItems()->pluck('label')->all();
    $child = MenuItem::query()->where('label', 'Sejarah')->sole();

    expect($roots)->toBe(['Kontak', 'Profil'])
        ->and($child->parent_id)->toBe($profil->id)
        ->and($child->href())->toBe('/sejarah');
});

test('menu links must use a safe scheme', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.menus')
        ->set('label', 'Bad')->set('type', 'url')->set('url', 'javascript:alert(1)')->call('save')
        ->assertHasErrors('url');
});
