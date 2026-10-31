<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\MediaAsset;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    Storage::fake('public');
    $this->pondok = onSite(SiteKey::Pondok);
});

test('uploads become site assets with image conversions', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.media')
        ->set('uploads', [UploadedFile::fake()->image('kegiatan-santri.jpg', 1200, 800)])
        ->assertHasNoErrors();

    $asset = MediaAsset::query()->sole();

    expect($asset->site_id)->toBe($this->pondok->id)
        ->and($asset->title)->toBe('Kegiatan Santri')
        ->and($asset->isImage())->toBeTrue()
        ->and($asset->file()->hasGeneratedConversion('thumb'))->toBeTrue();
});

test('disallowed file types are rejected', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin));

    Livewire::test('pages::admin.media')
        ->set('uploads', [UploadedFile::fake()->create('shell.php', 1)])
        ->assertHasErrors('uploads.0');

    expect(MediaAsset::query()->count())->toBe(0);
});

test('sites see their own and shared assets only', function () {
    $own = MediaAsset::factory()->create(['site_id' => $this->pondok->id, 'title' => 'Own']);
    $shared = MediaAsset::factory()->create(['title' => 'Shared']);
    $other = MediaAsset::factory()->create(['site_id' => Site::findByKey(SiteKey::Mahad)->id, 'title' => 'Other']);

    expect(MediaAsset::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$own->id, $shared->id])->sort()->values()->all())
        ->not->toContain($other->id);
});

test('site admins cannot change shared assets', function () {
    $shared = MediaAsset::factory()->create();
    $own = MediaAsset::factory()->create(['site_id' => $this->pondok->id]);
    $admin = cmsUser(Role::PondokAdmin);

    expect($admin->can('delete', $shared))->toBeFalse()
        ->and($admin->can('delete', $own))->toBeTrue()
        ->and(cmsUser(Role::ManagingEditor)->can('delete', $shared))->toBeTrue();
});

test('social media managers cannot delete media', function () {
    $own = MediaAsset::factory()->create(['site_id' => $this->pondok->id]);
    $this->actingAs(cmsUser(Role::SocialMediaManager));

    Livewire::test('pages::admin.media')->call('delete', $own->id)->assertForbidden();
});
