<?php

namespace App\Http\Middleware;

use App\Services\Shopify\ShopifyRequestConverter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shopify\App\ShopifyApp;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies that an incoming webhook request originates from Shopify.
 *
 * The X-Shopify-Hmac-SHA256 header must contain a base64-encoded HMAC-SHA256
 * digest of the raw request body, signed with the app's client secret.
 * Verification is delegated to shopify-app-php which responds with
 * 400 (missing HMAC header), 401 (invalid HMAC) or 405 (non-POST method).
 *
 * @see https://shopify.dev/docs/apps/build/webhooks/subscribe/https
 */
class VerifyShopifyWebhook
{
    public function __construct(protected ShopifyApp $shopifyApp) {}

    public function handle(Request $request, Closure $next): Response
    {
        $result = $this->shopifyApp->verifyWebhookReq(
            ShopifyRequestConverter::toShopifyRequest($request)
        );

        if (! $result->ok) {
            Log::warning('Shopify webhook verification failed', [
                'code' => $result->log->code,
                'detail' => $result->log->detail,
            ]);

            return ShopifyRequestConverter::toResponse($result->response, $result->log);
        }

        return $next($request);
    }
}
