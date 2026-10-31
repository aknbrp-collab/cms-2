<?php

use App\Http\Controllers\Admin\SwitchSiteController;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\SetAdminSite;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureAdminAccess::class, SetAdminSite::class])->group(function () {
    Route::livewire('dashboard', 'pages::admin.dashboard')->name('dashboard');

    Route::post('switch-site', SwitchSiteController::class)->name('admin.switch-site');

    // Content
    Route::livewire('posts', 'pages::admin.posts.index')->name('admin.posts.index');
    Route::livewire('posts/create', 'pages::admin.posts.edit')->name('admin.posts.create');
    Route::livewire('posts/{post}/edit', 'pages::admin.posts.edit')->name('admin.posts.edit');

    Route::livewire('pages', 'pages::admin.pages.index')->name('admin.pages.index');
    Route::livewire('pages/create', 'pages::admin.pages.edit')->name('admin.pages.create');
    Route::livewire('pages/{page}/edit', 'pages::admin.pages.edit')->name('admin.pages.edit');

    Route::livewire('categories', 'pages::admin.categories')
        ->middleware('can:categories.view')
        ->name('admin.categories');

    Route::livewire('review', 'pages::admin.review')
        ->middleware('can:review-content')
        ->name('admin.review');

    Route::livewire('media', 'pages::admin.media')
        ->middleware('can:media.view')
        ->name('admin.media');

    // Website
    Route::livewire('menus', 'pages::admin.menus')->name('admin.menus');

    Route::livewire('site-settings', 'pages::admin.site-settings')->name('admin.site-settings');

    // System
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
