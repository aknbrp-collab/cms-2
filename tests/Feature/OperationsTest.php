<?php

use App\Enums\Role;
use App\Models\IntegrationLog;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;

beforeEach(fn () => seedCms());

test('every response carries the security headers', function (string $url) {
    $this->get($url)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'")
        ->assertHeaderMissing('Strict-Transport-Security');
})->with([
    'admin login' => fn () => adminUrl('login'),
    'public site' => 'http://pondok.test/',
]);

test('hsts is only sent over https', function () {
    $this->get(str_replace('http://', 'https://', adminUrl('login')))
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('only superadmins can open horizon', function () {
    expect(Gate::forUser(cmsUser(Role::Superadmin))->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(cmsUser(Role::ManagingEditor))->allows('viewHorizon'))->toBeFalse();

    $this->actingAs(cmsUser(Role::PondokAdmin))->get(adminUrl('horizon'))->assertForbidden();
});

test('logins are throttled per account and per ip', function () {
    $user = cmsUser(Role::PondokAdmin);

    foreach (range(1, 5) as $attempt) {
        $this->post(adminUrl('login'), ['email' => $user->email, 'password' => 'wrong'])->assertRedirect();
    }
    $this->post(adminUrl('login'), ['email' => $user->email, 'password' => 'wrong'])->assertTooManyRequests();

    // The blocked attempt above did not count toward the per-IP limit: 5 used, 15 left.
    foreach (range(1, 15) as $attempt) {
        $this->post(adminUrl('login'), ['email' => "spray{$attempt}@example.com", 'password' => 'wrong'])->assertRedirect();
    }
    $this->post(adminUrl('login'), ['email' => 'spray-last@example.com', 'password' => 'wrong'])->assertTooManyRequests();
});

test('maintenance and backups are scheduled', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn (Event $event) => (string) $event->command)->implode("\n");

    foreach (['cms:publish-scheduled', 'social:dispatch-due', 'social:refresh-tokens', 'model:prune', 'queue:prune-failed', 'backup:clean', 'backup:run', 'backup:monitor', 'horizon:snapshot'] as $command) {
        expect($commands)->toContain($command);
    }
});

test('old integration logs are pruned', function () {
    $old = IntegrationLog::query()->create(['system' => 'siakad', 'direction' => 'inbound', 'action' => 'article', 'status' => 'success']);
    $old->forceFill(['created_at' => now()->subDays(181)])->save();
    $recent = IntegrationLog::query()->create(['system' => 'siakad', 'direction' => 'inbound', 'action' => 'article', 'status' => 'success']);

    $this->artisan('model:prune', ['--model' => IntegrationLog::class])->assertSuccessful();

    expect(IntegrationLog::query()->pluck('id')->all())->toBe([$recent->id]);
});

test('the readiness check fails on unsafe production settings', function () {
    config([
        'app.debug' => true,
        'app.url' => 'http://admin.pondok.test',
        'session.secure' => false,
        'mail.default' => 'log',
        'siakad.driver' => 'http',
        'siakad.base_url' => '',
    ]);

    $this->artisan('cms:check')
        ->expectsOutputToContain('must be false: debug pages leak secrets')
        ->expectsOutputToContain('must be true behind HTTPS')
        ->expectsOutputToContain('SIAKAD_BASE_URL and SIAKAD_API_KEY are required')
        ->expectsOutputToContain('PostgreSQL is required')
        ->assertFailed();
});
