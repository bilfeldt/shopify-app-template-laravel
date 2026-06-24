<?php

use App\Models\Shop;
use App\Services\Shopify\ShopifyShop;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

pest()->group('models', 'shop');

it('can be created using the factory', function () {
    $shop = Shop::factory()->create();

    expect($shop)->toBeInstanceOf(Shop::class)
        ->and($shop->id)->toBeInt()
        ->and($shop->shop_domain)->toEndWith('.myshopify.com')
        ->and($shop->name)->toBeString()
        ->and($shop->email)->toContain('@');
});

it('has fillable attributes', function (string $attribute, mixed $set, mixed $get) {
    $shop = Shop::factory()->make();
    $shop->fill([$attribute => $set]);
    $shop->save();
    $shop->refresh();

    expect($shop->{$attribute})->toBe($get);
})->with([
    ['shop_domain', 'demo-shop.myshopify.com', 'demo-shop.myshopify.com'],
    ['name', 'Test Shop', 'Test Shop'],
    ['email', 'owner@test-shop.com', 'owner@test-shop.com'],
]);

it('allows nullable attributes to be null', function (string $attribute) {
    $shop = Shop::factory()->create([$attribute => null]);
    $shop->refresh();

    expect($shop->{$attribute})->toBeNull();
})->with([
    'name',
    'email',
]);

it('extracts the Shopify subdomain from the shop domain', function () {
    $shop = Shop::factory()->create(['shop_domain' => 'my-awesome-shop.myshopify.com']);

    expect($shop->getShopifySubdomain())->toBe('my-awesome-shop');
});

describe('Authenticatable', function () {
    it('implements the Authenticatable interface', function () {
        $shop = Shop::factory()->create();

        expect($shop)->toBeInstanceOf(Authenticatable::class);
    });

    it('uses shop_domain as the auth identifier', function () {
        $shop = Shop::factory()->create(['shop_domain' => 'auth-test.myshopify.com']);

        expect($shop->getAuthIdentifierName())->toBe('shop_domain')
            ->and($shop->getAuthIdentifier())->toBe('auth-test.myshopify.com');
    });
});

it('exposes a ShopifyShop facade via shopify()', function () {
    config([
        'shopify.client_id' => 'test-client-id',
        'shopify.client_secret' => 'test-client-secret',
    ]);

    $shop = Shop::factory()->create();

    expect($shop->shopify())->toBeInstanceOf(ShopifyShop::class);
});
