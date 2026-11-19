<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\HeroSlide;
use App\Models\MediaAsset;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    Storage::fake('public');
    $this->mahad = Site::findByKey(SiteKey::Mahad);
});

function slideImage(Site $site, string $name = 'slide.jpg'): MediaAsset
{
    $asset = MediaAsset::query()->create(['site_id' => $site->id, 'title' => $name]);
    $asset->addMedia(UploadedFile::fake()->image($name, 1600, 700))->toMediaCollection('file');

    return $asset;
}

function slide(Site $site, array $attributes = []): HeroSlide
{
    return HeroSlide::query()->create(['site_id' => $site->id, 'image_id' => slideImage($site, ($attributes['title'] ?? 'slide').'.jpg')->id, ...$attributes]);
}

test('without slides the ma\'had hero stays as before', function () {
    $this->get('http://mahad.test/')
        ->assertOk()
        ->assertSee('<h1 class="mt-6 font-display', false)
        ->assertDontSee('aria-roledescription="carousel"', false);
});

test('active slides form the hero background in order, with their own texts', function () {
    slide($this->mahad, ['title' => 'Wisuda Angkatan VII', 'text' => 'Selamat kepada para wisudawan.', 'button_label' => 'Lihat galeri', 'button_url' => '/galeri', 'sort_order' => 2]);
    slide($this->mahad, ['title' => 'Penerimaan Mahasantri Baru', 'sort_order' => 1]);
    slide($this->mahad, ['title' => 'Slide Tersembunyi', 'sort_order' => 0])->forceFill(['is_hidden' => true])->save();
    slide($this->mahad, ['title' => 'Belum Dimulai', 'starts_at' => now()->addDay()]);
    slide($this->mahad, ['title' => 'Sudah Berakhir', 'ends_at' => now()->subDay()]);
    HeroSlide::query()->create(['site_id' => $this->mahad->id, 'title' => 'Tanpa Gambar']);

    $this->get('http://mahad.test/')
        ->assertOk()
        ->assertSee('aria-roledescription="carousel"', false)
        ->assertSee('<h1 class="sr-only">'.e($this->mahad->name).'</h1>', false)
        ->assertSeeInOrder(['Penerimaan Mahasantri Baru', 'Wisuda Angkatan VII'])
        ->assertSee('Selamat kepada para wisudawan.')
        ->assertSee('href="/galeri"', false)
        ->assertSee('Jeda slide otomatis')
        ->assertDontSee('Slide Tersembunyi')
        ->assertDontSee('Belum Dimulai')
        ->assertDontSee('Sudah Berakhir')
        ->assertDontSee('Tanpa Gambar');
});

test('a single slide has no carousel controls and falls back to the site name', function () {
    slide($this->mahad, ['title' => null]);

    $this->get('http://mahad.test/')
        ->assertOk()
        ->assertDontSee('aria-roledescription="carousel"', false)
        ->assertDontSee('Slide berikutnya')
        ->assertSee('fetchpriority="high"', false)
        ->assertSee('<p class="mt-6 font-display', false);
});

test('pondok slides are cards with a label and an optional link, shown only in its hero', function () {
    $pondok = Site::findByKey(SiteKey::Pondok);
    slide($pondok, ['title' => 'Slide Pondok']);

    // The Pondok hero is off until an admin switches it on.
    $this->get('http://pondok.test/')->assertOk()->assertDontSee('Slide Pondok');

    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));
    Livewire::test('pages::admin.hero-slides')
        ->assertSee('Photo cards in the hero')
        ->call('create')
        ->set('imageId', slideImage($pondok)->id)
        ->set('label', 'Prestasi Nasional')
        ->set('slideTitle', 'Juara Umum Pencak Silat')
        ->set('buttonUrl', '/berita/juara')
        ->call('save')
        ->assertHasNoErrors()
        ->call('create')
        ->set('imageId', slideImage($pondok)->id)
        ->set('buttonLabel', 'Daftar')
        ->call('save')
        ->assertHasErrors('buttonLabel');

    expect(HeroSlide::query()->where('label', 'Prestasi Nasional')->sole()->only(['title', 'button_url']))
        ->toBe(['title' => 'Juara Umum Pencak Silat', 'button_url' => '/berita/juara']);
});

test('ma\'had admins create, reorder, hide and delete slides', function () {
    onSite(SiteKey::Mahad);
    $this->actingAs(cmsUser(Role::MahadAdmin));
    $first = slide($this->mahad, ['title' => 'Pertama', 'sort_order' => 0]);
    $image = slideImage($this->mahad, 'baru.jpg');

    $component = Livewire::test('pages::admin.hero-slides')
        ->call('create')
        ->set('imageId', $image->id)
        ->set('slideTitle', 'Kedua')
        ->set('buttonLabel', 'Daftar')
        ->set('buttonUrl', '/penerimaan')
        ->set('startsAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    $second = HeroSlide::query()->where('title', 'Kedua')->sole();
    expect($second->sort_order)->toBe(1)->and($second->site_id)->toBe($this->mahad->id);

    $component->call('move', $second->id, -1);
    expect(HeroSlide::query()->orderBy('sort_order')->pluck('title')->all())->toBe(['Kedua', 'Pertama']);

    $component->call('toggleHidden', $first->id);
    expect($first->fresh()->is_hidden)->toBeTrue();

    $component->call('delete', $first->id);
    expect(HeroSlide::query()->count())->toBe(1)->and(MediaAsset::query()->whereKey($first->image_id)->exists())->toBeTrue();
});

test('slide input is validated', function () {
    onSite(SiteKey::Mahad);
    $this->actingAs(cmsUser(Role::MahadAdmin));

    Livewire::test('pages::admin.hero-slides')
        ->call('create')
        ->set('buttonLabel', 'Klik')
        ->set('buttonUrl', 'javascript:alert(1)')
        ->set('startsAt', '2026-12-10T10:00')
        ->set('endsAt', '2026-12-01T10:00')
        ->call('save')
        ->assertHasErrors(['imageId', 'buttonUrl', 'endsAt']);

    Livewire::test('pages::admin.hero-slides')
        ->call('create')
        ->set('imageId', slideImage($this->mahad)->id)
        ->set('buttonLabel', 'Klik')
        ->call('save')
        ->assertHasErrors(['buttonUrl']);
});

test('other roles cannot manage slides', function () {
    $slide = slide($this->mahad, ['title' => 'Milik Mahad']);

    onSite(SiteKey::Mahad);
    // Pondok admins have the slider for their own site, not for the Ma'had.
    $this->actingAs(cmsUser(Role::PondokAdmin));
    Livewire::test('pages::admin.hero-slides')->assertSee('or you do not manage it')->call('toggleHidden', $slide->id)->assertForbidden();

    $this->actingAs(cmsUser(Role::SocialMediaManager));
    Livewire::test('pages::admin.hero-slides')->assertForbidden();

    expect($slide->fresh()->is_hidden)->toBeFalse();
});
