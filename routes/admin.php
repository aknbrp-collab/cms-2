<?php

use App\Cms\Resources;
use App\Http\Controllers\Admin\ExportRegistrationsController;
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

    Route::livewire('events', 'pages::admin.events.index')->name('admin.events.index');
    Route::livewire('events/create', 'pages::admin.events.edit')->name('admin.events.create');
    Route::livewire('events/{event}/edit', 'pages::admin.events.edit')->name('admin.events.edit');

    Route::livewire('galleries', 'pages::admin.galleries.index')->name('admin.galleries.index');
    Route::livewire('galleries/create', 'pages::admin.galleries.edit')->name('admin.galleries.create');
    Route::livewire('galleries/{gallery}/edit', 'pages::admin.galleries.edit')->name('admin.galleries.edit');

    Route::livewire('faqs', 'pages::admin.faqs')->name('admin.faqs');
    Route::livewire('leaders', 'pages::admin.leaders')->name('admin.leaders');

    Route::livewire('admissions', 'pages::admin.admissions.index')->name('admin.admissions.index');
    Route::livewire('admissions/create', 'pages::admin.admissions.edit')->name('admin.admissions.create');
    Route::livewire('admissions/{period}/edit', 'pages::admin.admissions.edit')->name('admin.admissions.edit');
    Route::livewire('admissions/registrations', 'pages::admin.admissions.registrations')->name('admin.admissions.registrations');
    Route::get('admissions/registrations/export', ExportRegistrationsController::class)
        ->middleware('can:admissions.view')
        ->name('admin.admissions.export');

    Route::livewire('resources/{resource}', 'pages::admin.resource')
        ->whereIn('resource', array_keys(Resources::all()))
        ->name('admin.resources');
    Route::livewire('programs/{program}/curriculum', 'pages::admin.programs.curriculum')->name('admin.programs.curriculum');

    Route::livewire('publications', 'pages::admin.publications.index')->name('admin.publications.index');
    Route::livewire('publications/create', 'pages::admin.publications.edit')->name('admin.publications.create');
    Route::livewire('publications/{publication}/edit', 'pages::admin.publications.edit')->name('admin.publications.edit');

    // SIAKAD integration
    Route::livewire('siakad/articles', 'pages::admin.siakad-articles.index')->name('admin.siakad-articles.index');
    Route::livewire('siakad/articles/{article}', 'pages::admin.siakad-articles.show')->name('admin.siakad-articles.show');
    Route::livewire('tanya-hukum', 'pages::admin.legal-questions.index')->name('admin.legal-questions.index');
    Route::livewire('tanya-hukum/{question}', 'pages::admin.legal-questions.show')->name('admin.legal-questions.show');
    Route::livewire('integration-logs', 'pages::admin.integration-logs')
        ->middleware('can:integration_logs.view')
        ->name('admin.integration-logs');

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
