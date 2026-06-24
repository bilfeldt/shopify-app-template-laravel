<?php

use App\Http\Controllers\Auth\PatchIdTokenController;
use App\Http\Middleware\VerifyShopifyAppHome;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shopify Authentication Routes
|--------------------------------------------------------------------------
|
| The patch-id-token route must be publicly accessible (no auth middleware):
| App Bridge redirects here to recover a fresh session token. See
| App\Http\Controllers\Auth\PatchIdTokenController.
|
*/

Route::get('/auth/patch-id-token', PatchIdTokenController::class)->name('auth.patch-id-token');

/*
|--------------------------------------------------------------------------
| App Home routes
|--------------------------------------------------------------------------
|
| Routes rendered inside the Shopify Admin iframe. VerifyShopifyAppHome
| verifies the Shopify session token and the `auth:apphome` guard resolves
| the authenticated Shop. Add your embedded app pages inside this group.
|
*/

Route::middleware([VerifyShopifyAppHome::class, 'auth:apphome'])->group(function () {
    Route::view('/', 'home')->name('home');
});
