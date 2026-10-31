<?php

use App\Cms\Resources;
use App\Enums\AchievementLevel;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Http\Middleware\SetAdminSite;
use App\Models\Achievement;
use App\Models\Facility;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\Site;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
});

test('every resource screen renders', function (string $key) {
    $site = Site::findByKey(SiteKey::Mahad);

    $this->actingAs(cmsUser(Role::Superadmin))
        ->withSession([SetAdminSite::SESSION_KEY => $site->id])
        ->get(route('admin.resources', $key))
        ->assertOk()
        ->assertSee(Resources::find($key)->label());
})->with(array_keys(Resources::all()));

test('unknown resources return 404', function () {
    $this->actingAs(cmsUser(Role::Superadmin))->get('http://admin.pondok.test/resources/nope')->assertNotFound();
});

test('a site admin creates a pondok achievement through the generic editor', function () {
    $site = onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.resource', ['resource' => 'achievements'])
        ->call('create')
        ->set('data.title', 'Juara 1 Kaligrafi')
        ->set('data.student_name', 'Fatimah')
        ->set('data.level', AchievementLevel::National->value)
        ->set('data.achieved_on', '2026-10-01')
        ->call('save')
        ->assertHasNoErrors();

    $achievement = Achievement::query()->sole();
    expect($achievement->site_id)->toBe($site->id)
        ->and($achievement->level)->toBe(AchievementLevel::National)
        ->and($achievement->is_published)->toBeFalse();
});

test('generic editor validates fields', function () {
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.resource', ['resource' => 'achievements'])
        ->call('create')
        ->set('data.level', 'galactic')
        ->call('save')
        ->assertHasErrors(['data.title', 'data.student_name', 'data.level', 'data.achieved_on']);
});

test('slugs are generated and kept unique per site', function () {
    onSite(SiteKey::Mahad);
    $this->actingAs(cmsUser(Role::MahadAdmin));

    foreach (['Dr. Ahmad', 'Dr. Ahmad'] as $name) {
        Livewire::test('pages::admin.resource', ['resource' => 'lecturers'])
            ->call('create')
            ->set('data.name', $name)
            ->set('data.bio', '<p>Bio</p><script>x()</script>')
            ->call('save')
            ->assertHasNoErrors();
    }

    expect(Lecturer::query()->pluck('slug')->sort()->values()->all())->toBe(['dr-ahmad', 'dr-ahmad-2'])
        ->and(Lecturer::query()->first()->bio)->toBe('<p>Bio</p>');
});

test('site admins only manage modules of their own site', function () {
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::PondokAdmin));

    $this->get(route('admin.resources', 'lecturers'))->assertForbidden();

    $this->actingAs(cmsUser(Role::MahadAdmin));
    $facility = Facility::query()->create(['site_id' => Site::findByKey(SiteKey::Pondok)->id, 'name' => 'Masjid']);
    onSite(SiteKey::Mahad);

    $this->get(route('admin.resources', 'facilities'))->assertForbidden();
    Livewire::test('pages::admin.resource', ['resource' => 'facilities'])->assertForbidden();
    expect($facility->fresh()->name)->toBe('Masjid');
});

test('the curriculum editor manages courses of a program', function () {
    $site = onSite(SiteKey::Mahad);
    $program = Program::query()->create(['site_id' => $site->id, 'name' => 'Fiqh', 'slug' => 'fiqh', 'level' => 'ula']);
    $this->actingAs(cmsUser(Role::MahadAdmin));

    Livewire::test('pages::admin.programs.curriculum', ['program' => $program])
        ->call('create', 1)
        ->set('name', 'Ushul Fiqh')
        ->set('credits', 3)
        ->set('books', 'Al-Waraqat')
        ->call('save')
        ->assertHasNoErrors();

    expect($program->courses()->sole())
        ->semester->toBe(1)
        ->credits->toBe(3)
        ->books->toBe('Al-Waraqat');
});
