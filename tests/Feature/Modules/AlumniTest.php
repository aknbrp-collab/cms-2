<?php

use App\Enums\MenuLocation;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Alumnus;
use App\Models\Menu;
use App\Models\Site;
use App\Models\Testimonial;
use Database\Seeders\AlumniMenuSeeder;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    $this->pondok = Site::findByKey(SiteKey::Pondok);
});

function alumnus(Site $site, string $name, int $year, ?string $city = null, bool $published = true): Alumnus
{
    $alumnus = Alumnus::query()->create(['site_id' => $site->id, 'name' => $name, 'graduation_year' => $year, 'city' => $city]);
    $alumnus->forceFill(['is_published' => $published])->save();

    return $alumnus;
}

test('admins add alumni and testimonials', function () {
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.resource', ['resource' => 'alumni'])
        ->call('create')
        ->set('data.name', 'Ahmad Fauzi')
        ->set('data.graduation_year', 2018)
        ->set('data.city', 'Kendal')
        ->call('save')
        ->assertHasNoErrors()
        ->call('create')
        ->set('data.name', 'Tanpa Tahun')
        ->set('data.graduation_year', 0)
        ->call('save')
        ->assertHasErrors('data.graduation_year');

    Livewire::test('pages::admin.resource', ['resource' => 'testimonials'])
        ->call('create')
        ->set('data.name', 'Laila')
        ->set('data.quote', 'Pondok mengajarkan adab.')
        ->call('save')
        ->assertHasNoErrors()
        ->call('create')
        ->set('data.name', 'Kosong')
        ->call('save')
        ->assertHasErrors('data.quote');

    expect(Alumnus::query()->sole()->only(['name', 'graduation_year', 'city', 'site_id']))
        ->toBe(['name' => 'Ahmad Fauzi', 'graduation_year' => 2018, 'city' => 'Kendal', 'site_id' => $this->pondok->id])
        ->and(Testimonial::query()->sole()->quote)->toBe('Pondok mengajarkan adab.');
});

test('the alumni directory searches by name or city and filters by year', function () {
    alumnus($this->pondok, 'Ahmad Fauzi', 2018, 'Kendal');
    alumnus($this->pondok, 'Siti Nurhaliza', 2018, 'Semarang');
    alumnus($this->pondok, 'Hasan Basri', 2022, 'Kendal');
    alumnus($this->pondok, 'Belum Terbit', 2022, 'Batang', published: false);

    $this->get('http://pondok.test/alumni')
        ->assertOk()
        ->assertSeeInOrder(['Hasan Basri', 'Ahmad Fauzi', 'Siti Nurhaliza'])
        ->assertSee('3 alumni')
        ->assertDontSee('Belum Terbit');

    $this->get('http://pondok.test/alumni?q=kendal')->assertOk()->assertSee(['Ahmad Fauzi', 'Hasan Basri'])->assertDontSee('Siti Nurhaliza');
    $this->get('http://pondok.test/alumni?angkatan=2018')->assertOk()->assertSee(['Ahmad Fauzi', 'Siti Nurhaliza'])->assertDontSee('Hasan Basri');
    $this->get('http://pondok.test/alumni?q=zzz')->assertOk()->assertSee('Alumni tidak ditemukan.');
    $this->get('http://mahad.test/alumni')->assertNotFound();
});

test('testimonials show the quote, year and role of published alumni only', function () {
    $shown = Testimonial::query()->create(['site_id' => $this->pondok->id, 'name' => 'Muhammad Rizqi', 'graduation_year' => 2020, 'occupation' => 'Mahasiswa Al-Azhar', 'quote' => 'Bekal kitab kuning sangat membantu.']);
    $shown->forceFill(['is_published' => true])->save();
    Testimonial::query()->create(['site_id' => $this->pondok->id, 'name' => 'Draf', 'quote' => 'Belum terbit.']);

    $this->get('http://pondok.test/alumni/testimoni')
        ->assertOk()
        ->assertSee(['Muhammad Rizqi', 'Alumni 2020', 'Mahasiswa Al-Azhar', 'Bekal kitab kuning sangat membantu.'])
        ->assertDontSee('Belum terbit.');

    $this->get('http://pondok.test/sitemap.xml')
        ->assertSee('http://pondok.test/alumni', false)
        ->assertSee('http://pondok.test/alumni/testimoni', false);
});

test('the seeder adds the Alumni dropdown before Kontak, once', function () {
    $menu = Menu::for($this->pondok, MenuLocation::Header);
    $menu->items()->create(['label' => 'Galeri', 'type' => 'url', 'url' => '#', 'sort_order' => 0]);
    $menu->items()->create(['label' => 'Kontak', 'type' => 'url', 'url' => '/kontak', 'sort_order' => 1]);

    (new AlumniMenuSeeder)->run();
    (new AlumniMenuSeeder)->run();

    $topLevel = $menu->items()->whereNull('parent_id')->get();
    expect($topLevel->pluck('label')->all())->toBe(['Galeri', 'Alumni', 'Kontak'])
        ->and($topLevel->firstWhere('label', 'Alumni')->children->pluck('url', 'label')->all())
        ->toBe(['Data' => '/alumni', 'Testimoni' => '/alumni/testimoni']);
});
