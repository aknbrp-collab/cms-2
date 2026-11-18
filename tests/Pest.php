<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Site;
use App\Models\User;
use App\Siakad\Signature;
use App\Support\CurrentSite;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Seed both sites and all roles and permissions.
 */
function seedCms(): void
{
    test()->seed([SiteSeeder::class, RolesAndPermissionsSeeder::class]);
}

/**
 * Create a user holding the given CMS role.
 */
function cmsUser(Role $role, array $attributes = []): User
{
    return User::factory()->create($attributes)->assignRole($role->value);
}

/**
 * Make the given site the current site, as the admin site switcher would.
 */
function onSite(SiteKey $key): Site
{
    $site = Site::findByKey($key);
    app(CurrentSite::class)->set($site);

    return $site;
}

/**
 * Absolute URL on the admin domain.
 */
function adminUrl(string $path = '/'): string
{
    return 'http://'.config('cms.admin_domain').'/'.ltrim($path, '/');
}

/**
 * Send a JSON webhook signed like SIAKAD would.
 *
 * @param  array<string, mixed>  $payload
 */
function siakadCall(string $method, string $route, array $payload = [], ?int $timestamp = null, string $secret = 'test-secret'): TestResponse
{
    $body = $payload === [] ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers = Signature::headers($body, $secret, $timestamp);

    return test()->call($method, $route, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIAKAD_TIMESTAMP' => $headers['X-Siakad-Timestamp'],
        'HTTP_X_SIAKAD_SIGNATURE' => $headers['X-Siakad-Signature'],
    ], $body);
}

function articlePayload(array $overrides = []): array
{
    return [
        'id' => 'ART-1',
        'type' => 'journal_article',
        'title' => 'Metodologi Qiyas',
        'authors' => ['Dr. Mahbub Ridwan'],
        'year' => 2026,
        'abstract' => 'Abstrak',
        'body_html' => '<p>Isi</p><script>alert(1)</script>',
        'keywords' => ['qiyas'],
        ...$overrides,
    ];
}
