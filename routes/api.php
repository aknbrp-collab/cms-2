<?php

use App\Http\Controllers\Webhooks\MetaCallbackController;
use App\Http\Controllers\Webhooks\SiakadWebhookController;
use App\Http\Middleware\VerifySiakadSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SIAKAD webhooks
|--------------------------------------------------------------------------
|
| Signed calls from SIAKAD to the CMS, served on the admin domain.
| Contract: docs/siakad-api-spec.md
|
*/

Route::domain(config('cms.admin_domain'))
    ->prefix('webhooks/siakad')
    ->middleware(['throttle:siakad', VerifySiakadSignature::class])
    ->name('webhooks.siakad.')
    ->group(function () {
        Route::post('articles', [SiakadWebhookController::class, 'article'])->name('articles');
        Route::get('articles/{externalId}', [SiakadWebhookController::class, 'showArticle'])
            ->where('externalId', '[A-Za-z0-9._:-]+')
            ->name('articles.show');
        Route::delete('articles/{externalId}', [SiakadWebhookController::class, 'withdrawArticle'])
            ->where('externalId', '[A-Za-z0-9._:-]+')
            ->name('articles.withdraw');
        Route::post('answers', [SiakadWebhookController::class, 'answer'])->name('answers');
    });

/*
|--------------------------------------------------------------------------
| Meta app callbacks (data deletion, deauthorize)
|--------------------------------------------------------------------------
*/

Route::domain(config('cms.admin_domain'))
    ->prefix('social/meta')
    ->middleware('throttle:60,1')
    ->name('social.meta.')
    ->group(function () {
        Route::post('data-deletion', [MetaCallbackController::class, 'dataDeletion'])->name('data-deletion');
        Route::post('deauthorize', [MetaCallbackController::class, 'deauthorize'])->name('deauthorize');
    });
