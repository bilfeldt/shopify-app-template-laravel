<?php

use App\Actions\Auth\SaveTokenExchangeAccessTokenAction;
use App\Actions\RefreshAccessTokenAction;
use App\Events\ShopifyAccessTokenRefreshed;
use App\Models\AccessToken;
use App\Models\Shop;
use App\Services\Shopify\Exceptions\AccessTokenStillValidException;
use App\Services\Shopify\Exceptions\ShopifyServerErrorException;
use App\Services\Shopify\ShopifyShop;
use App\Services\Shopify\ShopifyShopFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Shopify\App\Types\Log;
use Shopify\App\Types\ResponseInfo;
use Shopify\App\Types\TokenExchangeAccessToken;
use Shopify\App\Types\TokenExchangeResult;

uses(RefreshDatabase::class);

pest()->group('actions', 'refresh-access-token');

it('refreshes, saves, and dispatches event', function () {
    Event::fake();

    $shop = Shop::factory()->create();
    AccessToken::factory()->for($shop)->offline()->create();
    $shop->refresh();

    $newToken = new TokenExchangeAccessToken(
        accessMode: 'offline',
        shop: $shop->getShopifySubdomain(),
        token: 'shpat_refreshed_token',
        expires: '2026-12-31T23:59:59Z',
        scope: 'read_products,write_orders',
        refreshToken: 'shprt_new_refresh',
        refreshTokenExpires: '2027-06-30T23:59:59Z',
        user: null,
    );

    $mockShopifyShop = Mockery::mock(ShopifyShop::class);
    $mockShopifyShop->shouldReceive('refreshAccessToken')
        ->once()
        ->andReturn($newToken);

    $mockFactory = Mockery::mock(ShopifyShopFactory::class);
    $mockFactory->shouldReceive('forShop')
        ->with($shop)
        ->once()
        ->andReturn($mockShopifyShop);

    $action = new RefreshAccessTokenAction(
        shopifyServiceFactory: $mockFactory,
        saveTokenAction: new SaveTokenExchangeAccessTokenAction,
    );

    $result = $action->execute($shop);

    expect($result)->toBeInstanceOf(AccessToken::class)
        ->and($result->token)->toBe('shpat_refreshed_token');

    Event::assertDispatched(ShopifyAccessTokenRefreshed::class, function ($event) use ($shop) {
        return $event->shop->is($shop);
    });
});

it('treats a still-valid token as a no-op and returns the existing token', function () {
    Event::fake();

    $shop = Shop::factory()->create();
    $token = AccessToken::factory()->for($shop)->offline()->create();
    $shop->refresh();

    $mockShopifyShop = Mockery::mock(ShopifyShop::class);
    $mockShopifyShop->shouldReceive('refreshAccessToken')
        ->once()
        ->andThrow(new AccessTokenStillValidException);

    $mockFactory = Mockery::mock(ShopifyShopFactory::class);
    $mockFactory->shouldReceive('forShop')->once()->andReturn($mockShopifyShop);

    $action = new RefreshAccessTokenAction(
        shopifyServiceFactory: $mockFactory,
        saveTokenAction: new SaveTokenExchangeAccessTokenAction,
    );

    $result = $action->execute($shop);

    expect($result->id)->toBe($token->id);

    Event::assertNotDispatched(ShopifyAccessTokenRefreshed::class);
});

it('propagates ShopifyAuthException subclasses from the underlying refresh', function () {
    $shop = Shop::factory()->create();
    AccessToken::factory()->for($shop)->offline()->create();
    $shop->refresh();

    $mockShopifyShop = Mockery::mock(ShopifyShop::class);
    $mockShopifyShop->shouldReceive('refreshAccessToken')
        ->once()
        ->andThrow(new ShopifyServerErrorException(
            new TokenExchangeResult(
                ok: false,
                shop: $shop->getShopifySubdomain(),
                accessToken: null,
                log: new Log(code: 'server_error', detail: 'Failed'),
                httpLogs: [],
                response: new ResponseInfo(status: 500, body: '', headers: (object) []),
            ),
        ));

    $mockFactory = Mockery::mock(ShopifyShopFactory::class);
    $mockFactory->shouldReceive('forShop')->once()->andReturn($mockShopifyShop);

    $action = new RefreshAccessTokenAction(
        shopifyServiceFactory: $mockFactory,
        saveTokenAction: new SaveTokenExchangeAccessTokenAction,
    );

    $action->execute($shop);
})->throws(ShopifyServerErrorException::class);

it('does not dispatch event on failure', function () {
    Event::fake();

    $shop = Shop::factory()->create();
    AccessToken::factory()->for($shop)->offline()->create();
    $shop->refresh();

    $mockShopifyShop = Mockery::mock(ShopifyShop::class);
    $mockShopifyShop->shouldReceive('refreshAccessToken')
        ->once()
        ->andThrow(new ShopifyServerErrorException(
            new TokenExchangeResult(
                ok: false,
                shop: $shop->getShopifySubdomain(),
                accessToken: null,
                log: new Log(code: 'server_error', detail: 'Failed'),
                httpLogs: [],
                response: new ResponseInfo(status: 500, body: '', headers: (object) []),
            ),
        ));

    $mockFactory = Mockery::mock(ShopifyShopFactory::class);
    $mockFactory->shouldReceive('forShop')->once()->andReturn($mockShopifyShop);

    $action = new RefreshAccessTokenAction(
        shopifyServiceFactory: $mockFactory,
        saveTokenAction: new SaveTokenExchangeAccessTokenAction,
    );

    try {
        $action->execute($shop);
    } catch (ShopifyServerErrorException) {
        // Expected
    }

    Event::assertNotDispatched(ShopifyAccessTokenRefreshed::class);
});
