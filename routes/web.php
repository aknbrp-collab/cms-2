<?php

use App\Http\Controllers\Site\DirectoryController;
use App\Http\Controllers\Site\ModuleController;
use App\Http\Controllers\Site\SiteController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Site\TanyaHukumController;
use App\Http\Middleware\ResolveSite;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
|
| Served on ADMIN_DOMAIN (e.g. admin.pondok.tld). Registered first so its
| routes win over the public site routes on that host.
|
*/

Route::domain(config('cms.admin_domain'))->group(function () {
    Route::redirect('/', '/dashboard')->name('home');

    require __DIR__.'/admin.php';
    require __DIR__.'/settings.php';
});

/*
|--------------------------------------------------------------------------
| Public websites
|--------------------------------------------------------------------------
|
| Any other host is matched against sites.domain by ResolveSite.
|
*/

Route::middleware(ResolveSite::class)->name('site.')->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('home');
    Route::get('berita', [SiteController::class, 'posts'])->name('posts.index');
    Route::get('berita/{slug}', [SiteController::class, 'post'])->name('posts.show');
    Route::get('pengumuman', [SiteController::class, 'announcements'])->name('announcements');
    Route::get('kategori/{slug}', [SiteController::class, 'category'])->name('categories.show');

    Route::get('agenda', [ModuleController::class, 'events'])->name('events.index');
    Route::get('agenda/{slug}', [ModuleController::class, 'event'])->name('events.show');
    Route::get('galeri', [ModuleController::class, 'galleries'])->name('galleries.index');
    Route::get('galeri/{slug}', [ModuleController::class, 'gallery'])->name('galleries.show');
    Route::get('faq', [ModuleController::class, 'faqs'])->name('faqs');
    Route::get('pimpinan', [ModuleController::class, 'leaders'])->name('leaders');
    Route::get('penerimaan', [ModuleController::class, 'admissions'])->name('admissions.show');
    Route::get('penerimaan/daftar', [ModuleController::class, 'registerForm'])->name('admissions.register');
    Route::post('penerimaan/daftar', [ModuleController::class, 'register'])
        ->middleware('throttle:admission-registration')
        ->name('admissions.store');
    Route::get('penerimaan/selesai/{number}', [ModuleController::class, 'registered'])
        ->middleware('signed')
        ->name('admissions.done');

    Route::get('fasilitas', [DirectoryController::class, 'facilities'])->name('facilities');
    Route::get('ekstrakurikuler', [DirectoryController::class, 'extracurriculars'])->name('extracurriculars');
    Route::get('prestasi', [DirectoryController::class, 'achievements'])->name('achievements');
    Route::get('dosen', [DirectoryController::class, 'lecturers'])->name('lecturers.index');
    Route::get('dosen/{slug}', [DirectoryController::class, 'lecturer'])->name('lecturers.show');
    Route::get('program', [DirectoryController::class, 'programs'])->name('programs.index');
    Route::get('program/{slug}', [DirectoryController::class, 'program'])->name('programs.show');
    Route::get('publikasi', [DirectoryController::class, 'publications'])->name('publications.index');
    Route::get('publikasi/{slug}', [DirectoryController::class, 'publication'])->name('publications.show');
    Route::get('jurnal', [DirectoryController::class, 'journals'])->name('journals.index');
    Route::get('jurnal/{slug}', [DirectoryController::class, 'journal'])->name('journals.show');
    Route::get('organisasi', [DirectoryController::class, 'organizations'])->name('organizations');

    Route::get('tanya-hukum', [TanyaHukumController::class, 'index'])->name('legal-questions.index');
    Route::get('tanya-hukum/tanya', [TanyaHukumController::class, 'create'])->name('legal-questions.create');
    Route::post('tanya-hukum/tanya', [TanyaHukumController::class, 'store'])
        ->middleware('throttle:legal-question')
        ->name('legal-questions.store');
    Route::get('tanya-hukum/status', [TanyaHukumController::class, 'status'])
        ->middleware('throttle:30,1')
        ->name('legal-questions.status');
    Route::get('tanya-hukum/{slug}', [TanyaHukumController::class, 'show'])->name('legal-questions.show');

    Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
    Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

    // Pages last, so their slugs never shadow the routes above.
    Route::get('{slug}', [SiteController::class, 'page'])
        ->where('slug', '[a-z0-9-]+')
        ->name('pages.show');
});
