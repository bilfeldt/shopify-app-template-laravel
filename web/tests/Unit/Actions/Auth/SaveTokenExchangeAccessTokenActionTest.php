<?php

use App\Actions\Auth\SaveTokenExchangeAccessTokenAction;
use App\Enums\AccessTokenMode;
use App\Models\AccessToken;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Shopify\App\Types\TokenExchangeAccessToken;

uses(RefreshDatabase::class);

pest()->group('actions', 'auth');

it('creates an access token for a shop', function () {
    $shop = Shop::factory()->create();
    $tokenExchange = new TokenExchangeAccessToken(
        accessMode: 'offline',
        shop: $shop->shop_domain,
        token: 'shpat_test_token_12345',
        expires: '2025-12-31T23:59:59Z',
        scope: 'read_products,write_orders',
        refreshToken: 'shprt_refresh_token_12345',
        refreshTokenExpires: '2026-06-30T23:59:59Z',
        user: null,
    );

    $action = new SaveTokenExchangeAccessTokenAction;
    $accessToken = $action->execute($shop, $tokenExchange);

    expect($accessToken)->toBeInstanceOf(AccessToken::class)
        ->and($accessToken->token)->toBe('shpat_test_token_12345')
        ->and($accessToken->mode)->toBe(AccessTokenMode::Offline)
        ->and($accessToken->scopes)->toBe(['read_products', 'write_orders'])
        ->and($accessToken->refresh_token)->toBe('shprt_refresh_token_12345');
});

it('updates existing access token instead of creating a new one', function () {
    $shop = Shop::factory()->create();
    $existingToken = AccessToken::factory()->create([
        'shop_id' => $shop->id,
        'token' => 'old_token',
    ]);

    $tokenExchange = new TokenExchangeAccessToken(
        accessMode: 'offline',
        shop: $shop->shop_domain,
        token: 'new_token',
        expires: null,
        scope: 'read_products',
        refreshToken: '',
        refreshTokenExpires: null,
        user: null,
    );

    $action = new SaveTokenExchangeAccessTokenAction;
    $action->execute($shop, $tokenExchange);

    expect(AccessToken::where('shop_id', $shop->id)->count())->toBe(1);
    $existingToken->refresh();
    expect($existingToken->token)->toBe('new_token');
});

it('reloads the accessToken relationship on the shop', function () {
    $shop = Shop::factory()->create();
    // Pre-load the relationship (to simulate it being cached as null)
    $shop->load('accessToken');

    $tokenExchange = new TokenExchangeAccessToken(
        accessMode: 'offline',
        shop: $shop->shop_domain,
        token: 'shpat_new_token',
        expires: null,
        scope: 'read_products',
        refreshToken: '',
        refreshTokenExpires: null,
        user: null,
    );

    $action = new SaveTokenExchangeAccessTokenAction;
    $action->execute($shop, $tokenExchange);

    // The relationship should be reloaded, not null
    expect($shop->accessToken)->not->toBeNull()
        ->and($shop->accessToken->token)->toBe('shpat_new_token');
});

it('logs the saved access token', function () {
    Log::spy();

    $shop = Shop::factory()->create();
    $tokenExchange = new TokenExchangeAccessToken(
        accessMode: 'offline',
        shop: $shop->shop_domain,
        token: 'shpat_logged_token',
        expires: null,
        scope: 'read_products',
        refreshToken: '',
        refreshTokenExpires: null,
        user: null,
    );

    $action = new SaveTokenExchangeAccessTokenAction;
    $accessToken = $action->execute($shop, $tokenExchange);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Saved AccessToken-model', [
            'shop_id' => $shop->id,
            'access_token_id' => $accessToken->id,
        ]);
});

it('correctly parses comma-separated scopes', function () {
    $shop = Shop::factory()->create();
    $tokenExchange = new TokenExchangeAccessToken(
        accessMode: 'offline',
        shop: $shop->shop_domain,
        token: 'shpat_scope_test',
        expires: null,
        scope: 'read_products,write_orders,read_customers,write_fulfillments',
        refreshToken: '',
        refreshTokenExpires: null,
        user: null,
    );

    $action = new SaveTokenExchangeAccessTokenAction;
    $accessToken = $action->execute($shop, $tokenExchange);

    expect($accessToken->scopes)->toBe([
        'read_products',
        'write_orders',
        'read_customers',
        'write_fulfillments',
    ]);
});
