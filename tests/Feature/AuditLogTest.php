<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;

beforeEach(fn () => seedCms());

test('creating a model is audited without hidden attributes', function () {
    $user = User::factory()->create();

    $log = AuditLog::query()->where('event', 'created')->where('auditable_id', $user->id)->sole();

    expect($log->new_values)
        ->toHaveKey('email', $user->email)
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

test('updates record only the changed values', function () {
    $user = User::factory()->create(['name' => 'Old']);

    $user->update(['name' => 'New']);

    $log = AuditLog::query()->where('event', 'updated')->where('auditable_id', $user->id)->sole();

    expect($log->old_values)->toBe(['name' => 'Old'])
        ->and($log->new_values)->toBe(['name' => 'New']);
});

test('password changes never leak into the audit log', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'another-password']);

    expect(AuditLog::query()->where('event', 'updated')->where('auditable_id', $user->id)->exists())->toBeFalse();
});

test('deleting a model is audited', function () {
    $user = User::factory()->create();
    $user->delete();

    expect(AuditLog::query()->where('event', 'deleted')->where('auditable_id', $user->id)->exists())->toBeTrue();
});

test('audit logs cannot be updated or deleted', function () {
    $log = Audit::record('test.event');

    expect(fn () => $log->update(['event' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

test('logins and failed logins are audited', function () {
    $user = cmsUser(Role::ManagingEditor);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect(AuditLog::query()->where('event', 'auth.failed')->sole()->meta)->toBe(['email' => $user->email])
        ->and(AuditLog::query()->where('event', 'auth.login')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('only users with audit permission can view audit logs', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin))->get(route('admin.audit-logs'))->assertForbidden();
    $this->actingAs(cmsUser(Role::ManagingEditor))->get(route('admin.audit-logs'))->assertOk();
});
