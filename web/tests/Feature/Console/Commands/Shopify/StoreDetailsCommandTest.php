<?php

use App\Models\AccessToken;
use App\Models\Shop;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shopify\App\ShopifyApp;
use Shopify\App\Types\GQLResult;
use Shopify\App\Types\Log;
use Shopify\App\Types\ResponseInfo;

uses(RefreshDatabase::class);

pest()->group('commands', 'shopify');

it('displays store details when --shop is provided', function () {
    $shop = Shop::factory()->create(['shop_domain' => 'test-store.myshopify.com']);
    AccessToken::factory()->for($shop)->create();

    $mockShopify = Mockery::mock(ShopifyApp::class);
    $mockShopify->shouldReceive('adminGraphQLRequest')->once()->andReturn(new GQLResult(
        ok: true,
        shop: 'test-store',
        data: [
            'shop' => [
                'id' => 'gid://shopify/Shop/1',
                'name' => 'Test Store',
                'myshopifyDomain' => 'test-store.myshopify.com',
                'currencyCode' => 'USD',
                'plan' => ['publicDisplayName' => 'Basic'],
                'contactEmail' => 'owner@test.com',
            ],
        ],
        extensions: null,
        log: new Log(code: 'success', detail: 'OK'),
        httpLogs: [],
        response: new ResponseInfo(status: 200, body: '', headers: (object) []),
    ));

    $this->app->instance(ShopifyApp::class, $mockShopify);

    $this->artisan('shopify:store-details', ['--shop' => 'test-store.myshopify.com'])
        ->assertSuccessful();
});

it('fails when --shop is provided but not found', function () {
    $this->artisan('shopify:store-details', ['--shop' => 'nonexistent.myshopify.com']);
})->throws(ModelNotFoundException::class, 'Shop not found: nonexistent.myshopify.com');
