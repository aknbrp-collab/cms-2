<?php

use App\Http\Controllers\Admin\SwitchSiteController;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\SetAdminSite;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureAdminAccess::class, SetAdminSite::class])->group(function () {
    Route::livewire('dashboard', 'pages::admin.dashboard')->name('dashboard');

    Route::post('switch-site', SwitchSiteController::class)->name('admin.switch-site');

    Route::livewire('users', 'pages::admin.users.index')
        ->middleware('can:users.view')
        ->name('admin.users.index');

    Route::livewire('roles', 'pages::admin.roles')
        ->middleware('can:roles.view')
        ->name('admin.roles');

    Route::livewire('audit-logs', 'pages::admin.audit-logs')
        ->middleware('can:audit_logs.view')
        ->name('admin.audit-logs');
});
