<?php

use Shopify\App\ShopifyApp;

pest()->group('auth', 'controllers');

beforeEach(function () {
    config([
        'shopify.client_id' => 'test-client-id',
        'shopify.client_secret' => 'test-client-secret',
    ]);
});

it('returns the app bridge html when request is valid', function () {
    $response = $this->get('/auth/patch-id-token?shop=test-shop.myshopify.com&shopify-reload=/some/path');

    $response->assertOk()
        ->assertSee('<script data-api-key="test-client-id" src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>', escape: false);

    expect($response->headers->get('Content-Type'))->toContain('text/html');
});

it('returns correct Content-Security-Policy header', function () {
    $response = $this->get('/auth/patch-id-token?shop=my-shop.myshopify.com&shopify-reload=/reload');

    $response->assertOk()
        ->assertHeader('Content-Security-Policy', 'frame-ancestors https://my-shop.myshopify.com https://admin.shopify.com;');
});

it('returns Link header for preloading app bridge', function () {
    $response = $this->get('/auth/patch-id-token?shop=test.myshopify.com&shopify-reload=/path');

    $response->assertOk()
        ->assertHeader('Link', '<https://cdn.shopify.com/shopifycloud/app-bridge.js>; rel="preload"; as="script";');
});

it('returns 400 when shop parameter is missing', function () {
    $response = $this->get('/auth/patch-id-token?shopify-reload=/path');

    $response->assertBadRequest();
});

it('returns 400 when shopify-reload parameter is missing', function () {
    $response = $this->get('/auth/patch-id-token?shop=test.myshopify.com');

    $response->assertBadRequest();
});

it('returns 400 when both parameters are missing', function () {
    $response = $this->get('/auth/patch-id-token');

    $response->assertBadRequest();
});

it('returns 500 when client_id is not configured', function () {
    config(['shopify.client_id' => null]);

    // Need to clear the singleton to pick up new config
    app()->forgetInstance(ShopifyApp::class);

    $response = $this->get('/auth/patch-id-token?shop=test.myshopify.com&shopify-reload=/path');

    $response->assertInternalServerError();
});

it('uses the correct route name', function () {
    expect(route('auth.patch-id-token'))->toEndWith('/auth/patch-id-token');
});
