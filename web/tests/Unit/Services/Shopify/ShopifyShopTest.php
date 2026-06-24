<?php

use App\Actions\RefreshAccessTokenAction;
use App\Models\AccessToken;
use App\Models\Shop;
use App\Services\Shopify\Exceptions\AccessTokenStillValidException;
use App\Services\Shopify\Exceptions\MissingAccessTokenException;
use App\Services\Shopify\Exceptions\MissingApiVersionException;
use App\Services\Shopify\Exceptions\ShopifyServerErrorException;
use App\Services\Shopify\GraphQL\ShopifyGraphQLClient;
use App\Services\Shopify\ShopifyShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shopify\App\ShopifyApp;
use Shopify\App\Types\Log;
use Shopify\App\Types\ResponseInfo;
use Shopify\App\Types\TokenExchangeAccessToken;
use Shopify\App\Types\TokenExchangeResult;

uses(RefreshDatabase::class);

pest()->group('services', 'shopify');

function createShopifyShop(Shop $shop, ?ShopifyApp $shopifyApp = null): ShopifyShop
{
    return new ShopifyShop(
        shop: $shop,
        shopifyApp: $shopifyApp ?? Mockery::mock(ShopifyApp::class),
    );
}

describe('isAccessTokenValid', function () {
    it('returns false when shop has no access token', function () {
        $shop = Shop::factory()->create();

        expect(createShopifyShop($shop)->isAccessTokenValid())->toBeFalse();
    });

    it('returns true when access token has no expiry', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offlineNonExpiring()->create();
        $shop->refresh();

        expect(createShopifyShop($shop)->isAccessTokenValid())->toBeTrue();
    });

    it('returns true when access token is not expired', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offline()->create(['expires_at' => now()->addHours(2)]);
        $shop->refresh();

        expect(createShopifyShop($shop)->isAccessTokenValid())->toBeTrue();
    });

    it('returns false when access token is expired', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->expired()->create();
        $shop->refresh();

        expect(createShopifyShop($shop)->isAccessTokenValid())->toBeFalse();
    });
});

describe('shouldAccessTokenBeRefreshed', function () {
    it('returns false when shop has no access token', function () {
        expect(createShopifyShop(Shop::factory()->create())->shouldAccessTokenBeRefreshed())->toBeFalse();
    });

    it('returns false when access token has no expiry', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offlineNonExpiring()->create();
        $shop->refresh();

        expect(createShopifyShop($shop)->shouldAccessTokenBeRefreshed())->toBeFalse();
    });

    it('returns false when access token expires far in the future', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offline()->create([
            'expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(90),
        ]);
        $shop->refresh();

        expect(createShopifyShop($shop)->shouldAccessTokenBeRefreshed())->toBeFalse();
    });

    it('returns true when access token is expired', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->expired()->create();
        $shop->refresh();

        expect(createShopifyShop($shop)->shouldAccessTokenBeRefreshed())->toBeTrue();
    });

    it('returns true when access token expires within threshold', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offline()->create(['expires_at' => now()->addMinutes(30)]);
        $shop->refresh();

        expect(createShopifyShop($shop)->shouldAccessTokenBeRefreshed(thresholdMinutes: 60))->toBeTrue();
    });

    it('returns true when refresh token expires within threshold', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offline()->create([
            'expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addMinutes(30),
        ]);
        $shop->refresh();

        expect(createShopifyShop($shop)->shouldAccessTokenBeRefreshed(thresholdMinutes: 60))->toBeTrue();
    });
});

describe('refreshAccessToken', function () {
    it('throws MissingAccessTokenException when shop has no access token', function () {
        createShopifyShop(Shop::factory()->create())->refreshAccessToken();
    })->throws(MissingAccessTokenException::class);

    it('throws a typed ShopifyAuthException matching the refresh log_code', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offline()->create();
        $shop->refresh();

        $mockShopify = Mockery::mock(ShopifyApp::class);
        $mockShopify->shouldReceive('refreshTokenExchangedAccessToken')
            ->once()
            ->andReturn(new TokenExchangeResult(
                ok: false,
                shop: $shop->getShopifySubdomain(),
                accessToken: null,
                log: new Log(code: 'server_error', detail: 'Internal server error'),
                httpLogs: [],
                response: new ResponseInfo(status: 500, body: 'Server Error', headers: (object) []),
            ));

        createShopifyShop($shop, $mockShopify)->refreshAccessToken();
    })->throws(ShopifyServerErrorException::class);

    it('throws AccessTokenStillValidException when token is still valid', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offline()->create();
        $shop->refresh();

        $mockShopify = Mockery::mock(ShopifyApp::class);
        $mockShopify->shouldReceive('refreshTokenExchangedAccessToken')
            ->once()
            ->andReturn(new TokenExchangeResult(
                ok: true,
                shop: $shop->getShopifySubdomain(),
                accessToken: null,
                log: new Log(code: 'token_still_valid', detail: 'Access token is still valid.'),
                httpLogs: [],
                response: new ResponseInfo(status: 200, body: '', headers: (object) []),
            ));

        createShopifyShop($shop, $mockShopify)->refreshAccessToken();
    })->throws(AccessTokenStillValidException::class);

    it('returns new TokenExchangeAccessToken on successful refresh', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offline()->create();
        $shop->refresh();

        $newToken = new TokenExchangeAccessToken(
            accessMode: 'offline',
            shop: $shop->getShopifySubdomain(),
            token: 'shpat_new_token',
            expires: '2026-12-31T23:59:59Z',
            scope: 'read_products,write_orders',
            refreshToken: 'shprt_new_refresh',
            refreshTokenExpires: '2027-06-30T23:59:59Z',
            user: null,
        );

        $mockShopify = Mockery::mock(ShopifyApp::class);
        $mockShopify->shouldReceive('refreshTokenExchangedAccessToken')
            ->once()
            ->andReturn(new TokenExchangeResult(
                ok: true,
                shop: $shop->getShopifySubdomain(),
                accessToken: $newToken,
                log: new Log(code: 'success', detail: 'Token refresh successful.'),
                httpLogs: [],
                response: new ResponseInfo(status: 200, body: '', headers: (object) []),
            ));

        $result = createShopifyShop($shop, $mockShopify)->refreshAccessToken();

        expect($result)->toBe($newToken)
            ->and($result->token)->toBe('shpat_new_token');
    });
});

describe('graphql', function () {
    it('throws MissingAccessTokenException when shop has no access token', function () {
        createShopifyShop(Shop::factory()->create())->graphql();
    })->throws(MissingAccessTokenException::class);

    it('throws MissingApiVersionException when the configured api version is empty', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offlineNonExpiring()->create();
        $shop->refresh();

        config(['shopify.api_version' => '']);

        createShopifyShop($shop)->graphql();
    })->throws(MissingApiVersionException::class);

    it('returns a ShopifyGraphQLClient bound to the shop', function () {
        $shop = Shop::factory()->create(['shop_domain' => 'demo.myshopify.com']);
        AccessToken::factory()->for($shop)->offlineNonExpiring()->create();
        $shop->refresh();

        config(['shopify.api_version' => '2025-01']);

        $client = createShopifyShop($shop)->graphql();

        expect($client)->toBeInstanceOf(ShopifyGraphQLClient::class)
            ->and($client->getShopDomain())->toBe('demo');
    });

    it('caches the client across calls', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offlineNonExpiring()->create();
        $shop->refresh();

        $service = createShopifyShop($shop);

        expect($service->graphql())->toBe($service->graphql());
    });

    it('proactively triggers a refresh when token is near expiry', function () {
        $shop = Shop::factory()->create();
        AccessToken::factory()->for($shop)->offline()->create(['expires_at' => now()->addSeconds(30)]);
        $shop->refresh();

        $action = Mockery::mock(RefreshAccessTokenAction::class);
        $action->shouldReceive('execute')->once()->with($shop)->andReturn($shop->accessToken);
        app()->instance(RefreshAccessTokenAction::class, $action);

        config(['shopify.api_version' => '2025-01']);

        createShopifyShop($shop)->graphql();
    });
});
