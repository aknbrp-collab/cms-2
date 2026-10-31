<?php

use App\Enums\SiteKey;
use App\Models\Site;

beforeEach(fn () => seedCms());

test('each domain serves its own site', function (string $host, SiteKey $key) {
    $this->get("http://{$host}/")
        ->assertOk()
        ->assertSee(e(Site::findByKey($key)->name), false);
})->with([
    'pondok' => ['pondok.test', SiteKey::Pondok],
    'mahad' => ['mahad.test', SiteKey::Mahad],
]);

test('unknown domains return not found', function () {
    $this->get('http://unknown.test/')->assertNotFound();
});

test('inactive sites return not found', function () {
    Site::findByKey(SiteKey::Mahad)->update(['is_active' => false]);

    $this->get('http://mahad.test/')->assertNotFound();
});

test('the admin domain root redirects to the dashboard', function () {
    $this->get(adminUrl())->assertRedirect('/dashboard');
});
