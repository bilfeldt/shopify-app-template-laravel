<?php

return [

    'client_id' => env('SHOPIFY_CLIENT_ID'),

    'client_secret' => env('SHOPIFY_CLIENT_SECRET'),

    /*
     * The Admin API version goes straight into the request URL
     * (`/admin/api/{version}/graphql.json`). Valid values are date versions
     * (e.g. `2025-01`) or `unstable`; there is no `latest` token — an
     * unrecognized value makes Shopify silently fall *forward* to the oldest
     * accessible stable version.
     *
     * Unset falls back to the default below (the newest version this app is
     * built and tested against). An explicit-but-empty SHOPIFY_API_VERSION is
     * deliberately left as an empty string so it is rejected loudly when a
     * GraphQL client is built — see ShopifyShop::graphql() and
     * MissingApiVersionException — rather than masking a misconfiguration.
     */
    'api_version' => env('SHOPIFY_API_VERSION', '2026-01'),

    'patch_id_token_path' => '/auth/patch-id-token',

    /*
    |--------------------------------------------------------------------------
    | Shopify App Bridge
    |--------------------------------------------------------------------------
    |
    | Configure the behaviour of Shopify App Bridge.
    |
    */
    'app_bridge' => [
        'js_cdn' => env('SHOPIFY_APP_BRIDGE_JS_CDN', 'https://cdn.shopify.com/shopifycloud/app-bridge.js'),
        'ui_js_cdn' => env('SHOPIFY_APP_BRIDGE_UI_JS_CDN', 'https://cdn.shopify.com/shopifycloud/app-bridge-ui-experimental.js'),
    ],
];
