<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Finder\SplFileInfo;

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    Storage::fake('public');
});

test('both sites apply the theme before the stylesheet and offer a toggle', function (string $host) {
    $html = $this->get("http://{$host}/")->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="color-scheme" content="light dark">')
        ->toContain("const key = 'site-theme';")
        ->toContain('window.siteTheme')
        ->toContain('aria-pressed')
        ->toContain('dark_mode')
        ->toContain('light_mode')
        ->and(strpos($html, 'window.siteTheme'))->toBeLessThan(strpos($html, '<link rel="stylesheet"'));
})->with(['pondok.test', 'mahad.test']);

test('a dark mode logo swaps in when uploaded', function () {
    $site = Site::findByKey(SiteKey::Pondok);
    $site->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('logo');

    $this->get('http://pondok.test/')->assertOk()->assertDontSee('object-contain dark:block', false);

    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::Superadmin));
    Livewire::test('pages::admin.site-settings')
        ->set('branding.logo_dark', UploadedFile::fake()->image('logo-dark.png'))
        ->call('save')
        ->assertHasNoErrors();

    $darkLogo = $site->fresh()->brandingUrl('logo_dark');
    expect($darkLogo)->not->toBe('');

    $this->get('http://pondok.test/')
        ->assertSee('object-contain dark:block', false)
        ->assertSee('src="'.$darkLogo.'"', false);
});

test('public views use the brand token, not primary, for text, borders and rings', function () {
    $files = collect(['sites', 'components/site', 'errors', 'legal', 'social'])
        ->flatMap(fn (string $directory) => File::allFiles(resource_path("views/{$directory}")));

    $offenders = $files
        ->filter(fn (SplFileInfo $file) => preg_match('/(?<![\w-])(?:[a-z-]+:)*(?:text|border|ring|accent)-primary(?![\w-])/', $file->getContents()))
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($files->count())->toBeGreaterThan(40)
        ->and($offenders)->toBe([]);
});
