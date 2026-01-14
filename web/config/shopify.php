<?php

return [

    'client_id' => env('SHOPIFY_CLIENT_ID'),

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