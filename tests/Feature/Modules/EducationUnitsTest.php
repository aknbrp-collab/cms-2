<?php

use App\Enums\MenuLocation;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\EducationUnit;
use App\Models\Menu;
use App\Models\Site;
use Database\Seeders\ProgramPendidikanSeeder;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    $this->pondok = Site::findByKey(SiteKey::Pondok);
});

test('admins create an education unit and build its curriculum', function () {
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.resource', ['resource' => 'education_units'])
        ->call('create')
        ->set('data.name', 'PDF Ulya')
        ->set('data.student_count', 245)
        ->set('data.summary', 'Pendidikan Diniyah Formal tingkat Ulya.')
        ->call('save')
        ->assertHasNoErrors();

    $unit = EducationUnit::query()->sole();
    expect($unit->slug)->toBe('pdf-ulya')
        ->and($unit->site_id)->toBe($this->pondok->id)
        ->and($unit->student_count)->toBe(245)
        ->and($unit->is_published)->toBeFalse();

    $curriculum = Livewire::test('pages::admin.education-units.curriculum', ['unit' => $unit]);
    foreach ([['Kelas 1', 'Nahwu', 'Al-Ajurumiyah'], ['Kelas 2', 'Fiqh', 'Fathul Qarib'], ['Kelas 1', 'Tauhid', 'Aqidatul Awam']] as [$grade, $name, $books]) {
        $curriculum->call('create')->set('grade', $grade)->set('name', $name)->set('books', $books)->call('save')->assertHasNoErrors();
    }
    $curriculum->call('create')->set('grade', '')->set('name', '')->call('save')->assertHasErrors(['grade', 'name']);

    expect($unit->subjects()->count())->toBe(3)
        ->and($unit->fresh()->curriculum()->keys()->all())->toBe(['Kelas 1', 'Kelas 2']);
});

test('the public pages show the student count and the curriculum grouped by class', function () {
    $unit = EducationUnit::query()->create(['site_id' => $this->pondok->id, 'name' => 'PDF Wustho', 'slug' => 'pdf-wustho', 'student_count' => 1250]);
    $unit->forceFill(['is_published' => true])->save();
    $unit->subjects()->create(['grade' => 'Kelas 1', 'name' => 'Nahwu', 'books' => 'Al-Ajurumiyah', 'sort_order' => 1]);
    $unit->subjects()->create(['grade' => 'Kelas 2', 'name' => 'Fiqh', 'books' => 'Fathul Qarib', 'sort_order' => 2]);
    $unit->subjects()->create(['grade' => 'Kelas 1', 'name' => 'Tauhid', 'sort_order' => 3]);
    EducationUnit::query()->create(['site_id' => $this->pondok->id, 'name' => 'Draf Tersembunyi', 'slug' => 'draf']);

    $this->get('http://pondok.test/program-pendidikan')
        ->assertOk()
        ->assertSee(['PDF Wustho', '1.250 santri', '3 mata pelajaran'])
        ->assertDontSee('Draf Tersembunyi');

    $this->get('http://pondok.test/program-pendidikan/pdf-wustho')
        ->assertOk()
        ->assertSee('1.250')
        ->assertSeeInOrder(['Kelas 1', 'Nahwu', 'Al-Ajurumiyah', 'Tauhid', 'Kelas 2', 'Fiqh', 'Fathul Qarib']);

    $this->get('http://pondok.test/program-pendidikan/draf')->assertNotFound();
    $this->get('http://mahad.test/program-pendidikan')->assertNotFound();
    $this->get('http://pondok.test/sitemap.xml')
        ->assertSee('http://pondok.test/program-pendidikan', false)
        ->assertSee('http://pondok.test/program-pendidikan/pdf-wustho', false)
        ->assertDontSee('/program-pendidikan/draf', false);
});

test('the seeder adds the three units and a dropdown after Profil, once', function () {
    $menu = Menu::for($this->pondok, MenuLocation::Header);
    $menu->items()->create(['label' => 'Profil', 'type' => 'url', 'url' => '#', 'sort_order' => 0]);
    $menu->items()->create(['label' => 'Berita', 'type' => 'url', 'url' => '/berita', 'sort_order' => 1]);

    (new ProgramPendidikanSeeder)->run();
    (new ProgramPendidikanSeeder)->run();

    expect(EducationUnit::withoutGlobalScopes()->published()->pluck('slug')->sort()->values()->all())->toBe(['pdf-ulya', 'pdf-wustho', 'tahfidz']);

    $topLevel = $menu->items()->whereNull('parent_id')->get();
    expect($topLevel->pluck('label')->all())->toBe(['Profil', 'Program Pendidikan', 'Berita']);

    $children = $topLevel->firstWhere('label', 'Program Pendidikan')->children;
    expect($children->pluck('label')->all())->toBe(['Tahfidz', 'PDF Wustho', 'PDF Ulya', "Ma'had Aly"])
        ->and($children->pluck('url')->all())->toBe(['/program-pendidikan/tahfidz', '/program-pendidikan/pdf-wustho', '/program-pendidikan/pdf-ulya', '#']);

    $this->get('http://pondok.test/program-pendidikan/tahfidz')->assertOk()->assertSee('Kurikulum belum tersedia.');
});
