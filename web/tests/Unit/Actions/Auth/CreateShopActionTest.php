<?php

use App\Actions\Auth\CreateShopAction;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

pest()->group('actions', 'auth');

it('creates a shop with the given domain', function () {
    $action = new CreateShopAction;
    $shopDomain = 'test-shop.myshopify.com';

    $shop = $action->execute($shopDomain);

    expect($shop)->toBeInstanceOf(Shop::class)
        ->and($shop->shop_domain)->toBe($shopDomain)
        ->and($shop->exists)->toBeTrue();
});

it('persists the shop to the database', function () {
    $action = new CreateShopAction;
    $shopDomain = 'persisted-shop.myshopify.com';

    $action->execute($shopDomain);

    $this->assertDatabaseHas('shops', [
        'shop_domain' => $shopDomain,
    ]);
});

it('logs the shop creation', function () {
    Log::spy();

    $action = new CreateShopAction;
    $shopDomain = 'logged-shop.myshopify.com';

    $action->execute($shopDomain);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Creating new Shop-model', ['shop_domain' => $shopDomain]);
});

it('returns the created shop with an id', function () {
    $action = new CreateShopAction;
    $shopDomain = 'shop-with-id.myshopify.com';

    $shop = $action->execute($shopDomain);

    expect($shop->id)->toBeInt()
        ->and($shop->id)->toBeGreaterThan(0);
});
