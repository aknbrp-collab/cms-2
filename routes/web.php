<?php

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
    Route::get('/', function () {
        return view('sites.home', ['site' => current_site()]);
    })->name('home');
});
