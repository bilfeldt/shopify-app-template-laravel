<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Shopify\ShopifyRequestConverter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Shopify\App\ShopifyApp;

class PatchIdTokenController extends Controller
{
    public function __construct(
        private ShopifyApp $shopify
    ) {}

    /**
     * Handle the patch ID token request from Shopify.
     *
     * The user is redirected to this endpoint in access token edge cases
     * like expiration or invalidity, ensuring the merchant experience is resilient.
     * This is recommended in the README.md of the Shopify/shopify-app-php package
     * but otherwise not mentioned in the docs.
     *
     * @see https://github.com/Shopify/shopify-app-php
     */
    public function __invoke(Request $request): Response
    {
        Log::warning('Handling Patch Id Token request');

        $shopifyRequest = ShopifyRequestConverter::toShopifyRequest($request);
        $result = $this->shopify->appHomePatchIdToken($shopifyRequest);

        Log::info('Patch Id Token request handled', [
            'log_code' => $result->log?->code,
            'log_detail' => $result->log?->detail,
            'response_status' => $result->response->status,
            'response_body' => $result->response->body,
        ]);

        return ShopifyRequestConverter::toResponse($result->response, $result->log);
    }
}
