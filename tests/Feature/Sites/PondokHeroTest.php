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
    $this->pondok = Site::findByKey(SiteKey::Pondok);
});

function heroSettings(Site $site, array $hero): void
{
    $values = $site->allSettings();
    foreach ($hero as $key => $value) {
        $values["hero.{$key}"] = $value;
    }
    $site->fillSettings($values)->save();
}

function pondokSlide(Site $site, array $attributes): HeroSlide
{
    $asset = MediaAsset::query()->create(['site_id' => $site->id, 'title' => 'foto']);
    $asset->addMedia(UploadedFile::fake()->image('foto.jpg', 1200, 900))->toMediaCollection('file');

    return HeroSlide::query()->create(['site_id' => $site->id, 'image_id' => $asset->id, ...$attributes]);
}

test('the hero is off until it is switched on and has a title', function () {
    heroSettings($this->pondok, ['title' => 'Mendidik Generasi Berakhlak Mulia']);
    $this->get('http://pondok.test/')->assertOk()->assertDontSee('Mendidik Generasi Berakhlak Mulia')->assertSee('<h1 class="sr-only">', false);

    heroSettings($this->pondok, ['enabled' => true, 'title' => '']);
    $this->get('http://pondok.test/')->assertOk()->assertSee('<h1 class="sr-only">', false);
});

test('the hero shows the admin text, buttons, slides and highlights', function () {
    heroSettings($this->pondok, [
        'enabled' => true,
        'badge' => 'Penerimaan Santri Baru (PSB) 2026/2027 Dibuka',
        'title' => 'Mendidik Generasi Berakhlak Mulia',
        'text' => 'Memadukan kurikulum kepesantrenan modern.',
        'primary_label' => 'Daftar Santri Baru (Online)',
        'primary_url' => 'https://santri.example.test',
        'primary_new_tab' => true,
        'brochure_label' => 'Unduh Brosur & Panduan Biaya',
        'brochure_url' => 'https://drive.example.test/brosur.pdf',
        'tour_label' => 'Virtual Tour Kampus',
        'tour_url' => 'https://youtube.example.test/tour',
        'slider_badge' => 'Pondok Berprestasi',
        'stat1_icon' => 'verified_user',
        'stat1_title' => 'Akreditasi A',
        'stat1_text' => 'Unggul BAN-S/M & Kemenag',
        'stat3_icon' => 'groups',
        'stat3_title' => '3.200+ Santri',
    ]);
    pondokSlide($this->pondok, ['label' => 'Prestasi Nasional', 'title' => 'Juara Umum Pencak Silat', 'button_url' => '/berita/juara', 'sort_order' => 1]);
    pondokSlide($this->pondok, ['label' => 'Pengembangan Karakter', 'title' => 'Apel Akbar Santri', 'sort_order' => 2]);

    $this->get('http://pondok.test/')
        ->assertOk()
        ->assertDontSee('<h1 class="sr-only">', false)
        ->assertSeeInOrder([
            'Penerimaan Santri Baru (PSB) 2026/2027 Dibuka',
            'Mendidik Generasi Berakhlak Mulia',
            'Memadukan kurikulum kepesantrenan modern.',
            'Daftar Santri Baru (Online)',
            'Unduh Brosur &amp; Panduan Biaya',
            'Virtual Tour Kampus',
            'Prestasi Nasional', 'Juara Umum Pencak Silat',
            'Pengembangan Karakter', 'Apel Akbar Santri',
            'Pondok Berprestasi',
            'Akreditasi A', 'Unggul BAN-S/M &amp; Kemenag',
            '3.200+ Santri',
        ], false)
        ->assertSee('href="https://santri.example.test"', false)
        ->assertSee('target="_blank" rel="noopener noreferrer"', false)
        ->assertSee('href="https://drive.example.test/brosur.pdf"', false)
        ->assertSee('href="/berita/juara"', false)
        ->assertSee('aria-roledescription="carousel"', false)
        ->assertSee('md:grid-cols-2', false);

    $this->get('http://mahad.test/')->assertDontSee('Mendidik Generasi Berakhlak Mulia');
});

test('admins edit the hero in site settings, with an uploaded brochure', function () {
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.site-settings')
        ->set('tab', 'hero')
        ->assertSee('Badge on the slider')
        ->set('settings.hero__enabled', true)
        ->set('settings.hero__title', '')
        ->set('settings.hero__primary_label', 'Daftar')
        ->set('settings.hero__tour_url', 'javascript:alert(1)')
        ->set('settings.hero__stat1_icon', 'not-an-icon')
        ->call('save')
        ->assertHasErrors(['settings.hero__title', 'settings.hero__primary_url', 'settings.hero__tour_url', 'settings.hero__tour_label', 'settings.hero__stat1_icon'])
        ->set('settings.hero__title', 'Mendidik Generasi')
        ->set('settings.hero__primary_url', '/penerimaan/daftar')
        ->set('settings.hero__tour_label', 'Virtual Tour')
        ->set('settings.hero__tour_url', 'https://youtube.example.test/tour')
        ->set('settings.hero__stat1_icon', 'military_tech')
        ->set('settings.hero__stat1_title', 'Top 5 Pesantren')
        ->set('settings.hero__brochure_label', 'Unduh Brosur')
        ->set('settings.hero__brochure_url', 'https://drive.example.test/lama.pdf')
        ->set('branding.hero_brochure', UploadedFile::fake()->create('brosur.pdf', 300, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors();

    $site = $this->pondok->fresh();
    expect($site->setting('hero.enabled'))->toBeTrue()
        ->and($site->setting('hero.stat1_icon'))->toBe('military_tech')
        ->and($site->brandingUrl('hero_brochure'))->toEndWith('brosur.pdf');

    // The uploaded file wins over the link.
    $this->get('http://pondok.test/')
        ->assertSee('Mendidik Generasi')
        ->assertSee('href="'.$site->brandingUrl('hero_brochure').'"', false)
        ->assertDontSee('lama.pdf');

    Livewire::test('pages::admin.site-settings')->call('removeBranding', 'hero_brochure');
    expect($site->fresh()->brandingUrl('hero_brochure'))->toBe('');
});

test('the hero tab is only offered on the Pondok site', function () {
    onSite(SiteKey::Mahad);
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.site-settings')->assertDontSee('Home hero')->set('tab', 'hero')->assertDontSee('Badge on the slider');
});
