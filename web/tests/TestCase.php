<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure ShopifyApp can construct without a real .env. AppServiceProvider
        // reads config('shopify.client_id') / client_secret when the apphome
        // guard is first resolved (e.g. via actingAs); without these fakes the
        // shopify-app-php constructor throws.
        config([
            'shopify.client_id' => config('shopify.client_id') ?: 'test-client-id',
            'shopify.client_secret' => config('shopify.client_secret') ?: 'test-client-secret',
        ]);
    }
}
