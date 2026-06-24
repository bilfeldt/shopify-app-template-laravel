<?php

use Shopify\App\ShopifyApp;

describe('ShopifyApp resolving ', function () {
    it('resolves from the container', function () {
        config([
            'shopify.client_id' => 'test-client-id',
            'shopify.client_secret' => 'test-client-secret',
        ]);

        $shopify = app(ShopifyApp::class);

        // The ShopifyApp class doesn't expose its config, but we can verify
        // it was instantiated without errors, meaning the config was valid
        expect($shopify)->toBeInstanceOf(ShopifyApp::class);
    });

    it('resolves as a singleton', function () {
        config([
            'shopify.client_id' => 'test-client-id',
            'shopify.client_secret' => 'test-client-secret',
        ]);

        $instance1 = app(ShopifyApp::class);
        $instance2 = app(ShopifyApp::class);

        expect($instance1 === $instance2)->toBeTrue(); // strict comparison
    });

    it('throws exception when client_id is missing', function () {
        config([
            'shopify.client_id' => null,
            'shopify.client_secret' => 'test-client-secret',
        ]);

        app(ShopifyApp::class);
    })->throws(TypeError::class);

    it('throws exception when client_secret is missing', function () {
        config([
            'shopify.client_id' => 'test-client-id',
            'shopify.client_secret' => null,
        ]);

        app(ShopifyApp::class);
    })->throws(InvalidArgumentException::class, 'clientSecret is required');
});
