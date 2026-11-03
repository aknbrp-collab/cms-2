<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\AdmissionPeriod;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Admin strings are English keys translated in lang/id.json. Public site
 * views and controllers are written in Indonesian and are not scanned.
 */
function adminTranslationKeys(): array
{
    $public = ['resources/views/sites/', 'resources/views/components/site/', 'resources/views/errors/', 'resources/views/legal/',
        'resources/views/social/', 'resources/views/components/legal-layout', 'app/Http/Controllers/Site/', 'app/Http/Requests/Site/'];
    $pattern = '/(?:__|trans_choice|trans)\(\s*([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1|#\[Title\(\s*([\'"])((?:(?!\3)[^\\\\]|\\\\.)*)\3/';
    $keys = [];

    $files = collect([...File::allFiles(app_path()), ...File::allFiles(resource_path('views'))])
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php');

    foreach ($files as $file) {
        $relative = str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()));

        if (str_starts_with($relative, '_') || collect($public)->contains(fn (string $prefix) => str_starts_with($relative, $prefix))) {
            continue;
        }

        preg_match_all($pattern, $file->getContents(), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            [$quote, $raw] = ($match[1] ?? '') !== '' ? [$match[1], $match[2]] : [$match[3], $match[4]];
            // PHP unescaping: \\ and the matching quote.
            $keys[strtr($raw, ['\\\\' => '\\', '\\'.$quote => $quote])] = $relative;
        }
    }

    return $keys;
}

test('every admin string has an indonesian translation', function () {
    $translations = json_decode((string) file_get_contents(lang_path('id.json')), true, flags: JSON_THROW_ON_ERROR);
    $keys = adminTranslationKeys();

    $missing = array_diff_key($keys, $translations);

    expect(count($keys))->toBeGreaterThan(800)
        ->and($missing)->toBe([], 'Add these strings to lang/id.json: '.implode(' | ', array_keys($missing)));
});

test('indonesian translations keep their placeholders', function () {
    $translations = json_decode((string) file_get_contents(lang_path('id.json')), true, flags: JSON_THROW_ON_ERROR);
    $placeholders = fn (string $text) => collect(preg_match_all('/:[a-zA-Z_]+/', $text, $found) ? $found[0] : [])
        ->map(fn (string $placeholder) => strtolower($placeholder))->unique()->sort()->values()->all();

    $broken = collect($translations)
        ->filter(fn (string $translation, string $key) => $placeholders($key) !== $placeholders($translation))
        ->keys()
        ->all();

    expect($broken)->toBe([]);
});

test('the admin panel speaks indonesian', function () {
    seedCms();
    app()->setLocale('id');
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::Superadmin));

    $this->get(route('admin.posts.index'))
        ->assertOk()
        ->assertSee('<title>', false)
        ->assertSee('Postingan')
        ->assertSee('Berita, pengumuman, dan artikel.')
        ->assertSee('Pengaturan situs')
        ->assertSee('Antrean tinjauan')
        ->assertSee('Dashboard')
        ->assertSee('Audit log')
        ->assertDontSee('News, announcements and articles.');

    $errors = validator(['name' => '', 'title' => str_repeat('a', 300)], ['name' => 'required', 'title' => 'max:255'])->errors();

    expect($errors->first('name'))->toBe('Nama wajib diisi.')
        ->and($errors->first('title'))->toBe('Judul tidak boleh lebih dari 255 karakter.');
});

test('public forms show indonesian validation messages', function () {
    seedCms();
    app()->setLocale('id');
    $this->withoutVite();
    AdmissionPeriod::factory()->for(onSite(SiteKey::Pondok))->published()->create();

    $this->get('http://pondok.test/penerimaan/daftar');
    $this->travel(10)->seconds();

    $this->from('http://pondok.test/penerimaan/daftar')
        ->post('http://pondok.test/penerimaan/daftar', ['agree' => '1'])
        ->assertSessionHasErrors(['full_name' => 'Nama lengkap wajib diisi.']);
});
