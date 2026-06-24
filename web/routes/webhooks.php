<?php

use App\Http\Controllers\Shopify\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shopify Webhook Routes
|--------------------------------------------------------------------------
|
| Single canonical endpoint receiving all Shopify webhook topics declared in
| shopify.app.toml (app/uninstalled and the GDPR compliance topics). These
| routes are registered without the web/api middleware groups (no session,
| no CSRF) and are protected by the VerifyShopifyWebhook HMAC middleware
| applied in bootstrap/app.php.
|
*/

Route::post('/webhooks/shopify', WebhookController::class)->name('webhooks.shopify');
