<?php

use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Event;
use App\Models\Gallery;
use App\Services\ContentWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    Storage::fake('public');
    $this->withoutVite();
    $this->pondok = onSite(SiteKey::Pondok);
});

test('a site admin creates an event and it goes through review', function () {
    $admin = cmsUser(Role::PondokAdmin);
    $this->actingAs($admin);

    Livewire::test('pages::admin.events.edit')
        ->set('title', 'Haflah Akhirussanah')
        ->set('startsAt', now()->addWeek()->format('Y-m-d\TH:i'))
        ->set('endsAt', now()->addWeek()->addHours(3)->format('Y-m-d\TH:i'))
        ->set('location', 'Aula')
        ->call('save')
        ->assertHasNoErrors();

    $event = Event::query()->sole();
    expect($event->site_id)->toBe($this->pondok->id)->and($event->slug)->toBe('haflah-akhirussanah');

    app(ContentWorkflow::class)->submit($event, $admin);
    $this->actingAs(cmsUser(Role::ManagingEditor))->get(route('admin.review'))->assertSee('Haflah Akhirussanah');
});

test('event end must not be before its start', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.events.edit')
        ->set('title', 'Salah Tanggal')
        ->set('startsAt', now()->addWeek()->format('Y-m-d\TH:i'))
        ->set('endsAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('endsAt');
});

test('the public agenda lists published upcoming events and an archive', function () {
    Event::factory()->for($this->pondok)->status(ContentStatus::Published)->create(['title' => 'Kajian Akan Datang']);
    Event::factory()->for($this->pondok)->status(ContentStatus::Published)->create(['title' => 'Kajian Lalu', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subMonth()->addHour()]);
    Event::factory()->for($this->pondok)->create(['title' => 'Kajian Draft']);

    $this->get('http://pondok.test/agenda')->assertOk()->assertSee('Kajian Akan Datang')->assertDontSee('Kajian Lalu')->assertDontSee('Kajian Draft');
    $this->get('http://pondok.test/agenda?arsip=1')->assertOk()->assertSee('Kajian Lalu')->assertDontSee('Kajian Akan Datang');
    $this->get('http://pondok.test/')->assertSee('Kajian Akan Datang');
});

test('galleries accept photos and youtube videos only', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin));
    $photos = Gallery::factory()->for($this->pondok)->create();
    $videos = Gallery::factory()->for($this->pondok)->video()->create();

    Livewire::test('pages::admin.galleries.edit', ['gallery' => $photos])
        ->set('photos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
        ->assertHasNoErrors();

    expect($photos->items()->count())->toBe(2);

    Livewire::test('pages::admin.galleries.edit', ['gallery' => $videos])
        ->set('videoUrl', 'https://evil.example.com/v')
        ->call('addVideo')
        ->assertHasErrors('videoUrl')
        ->set('videoUrl', 'https://youtu.be/abcdefghijk')
        ->set('videoCaption', 'Wisuda')
        ->call('addVideo')
        ->assertHasNoErrors();

    expect($videos->items()->sole()->embedUrl())->toBe('https://www.youtube-nocookie.com/embed/abcdefghijk');
});

test('published galleries show on the site and videos on the pondok homepage', function () {
    $gallery = Gallery::factory()->for($this->pondok)->video()->status(ContentStatus::Published)->create(['title' => 'Darul Amanah TV', 'slug' => 'tv']);
    $gallery->items()->create(['video_url' => 'https://youtu.be/abcdefghijk', 'caption' => 'Wisuda 2026']);

    $this->get('http://pondok.test/galeri/tv')->assertOk()->assertSee('youtube-nocookie.com/embed/abcdefghijk', false);
    $this->get('http://pondok.test/')->assertSee('Darul Amanah TV')->assertSee('youtube-nocookie.com/embed/abcdefghijk', false);
    $this->get('http://pondok.test/sitemap.xml')->assertSee('http://pondok.test/galeri/tv', false);
});

test('scheduled events are released by the scheduler', function () {
    $editor = cmsUser(Role::ManagingEditor);
    $event = Event::factory()->for($this->pondok)->create();

    app(ContentWorkflow::class)->publish($event, $editor, now()->addHour());
    $this->travel(2)->hours();
    $this->artisan('cms:publish-scheduled')->assertSuccessful();

    expect($event->fresh()->status)->toBe(ContentStatus::Published);
});
