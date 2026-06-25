<?php

use App\Auth\Guards\ShopifyAppHomeGuard;
use App\Enums\AccessTokenMode;
use App\Models\AccessToken;
use App\Models\Shop;
use App\Services\Shopify\Exceptions\ShopifyCredentialMismatchException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Shopify\App\ShopifyApp;
use Shopify\App\Types\IdToken;
use Shopify\App\Types\Log;
use Shopify\App\Types\LogWithReq;
use Shopify\App\Types\ResponseInfo;
use Shopify\App\Types\ResultWithExchangeableIdToken;
use Shopify\App\Types\TokenExchangeAccessToken;
use Shopify\App\Types\TokenExchangeResult;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    config([
        'shopify.client_id' => 'test-client-id',
        'shopify.client_secret' => 'test-client-secret',
    ]);
});

it('shopify app home guard registered', function () {
    $guard = Auth::guard('apphome');

    expect($guard)->toBeInstanceOf(ShopifyAppHomeGuard::class);
});

it('can set Shop model as the authenticated user via Auth facade', function () {
    $shop = Shop::factory()->create();

    Auth::guard('apphome')->setUser($shop);

    expect(Auth::guard('apphome')->check())->toBeTrue()
        ->and(Auth::guard('apphome')->user())->toBe($shop)
        ->and(Auth::guard('apphome')->id())->toBe($shop->shop_domain);
});

it('returns null when invalid token', function () {
    $mockShopify = Mockery::mock(ShopifyApp::class);
    $mockShopify->shouldReceive('verifyAppHomeReq')
        ->once()
        ->andReturn(new ResultWithExchangeableIdToken(
            ok: false,
            shop: null,
            idToken: null,
            userId: null,
            newIdTokenResponse: null,
            log: new LogWithReq(
                code: 'invalid_token',
                detail: 'Token verification failed',
                req: []
            ),
            response: new ResponseInfo(
                status: 401,
                body: 'Unauthorized',
                headers: []
            )
        ));

    $this->app->instance(ShopifyApp::class, $mockShopify);

    $guard = Auth::guard('apphome');

    expect($guard->check())->toBeFalse()
        ->and($guard->user())->toBeNull();
});

describe('credential mismatch diagnostic', function () {
    $mockVerify = function (string $code): void {
        $mockShopify = Mockery::mock(ShopifyApp::class);
        $mockShopify->shouldReceive('verifyAppHomeReq')
            ->andReturn(new ResultWithExchangeableIdToken(
                ok: false,
                shop: null,
                idToken: null,
                userId: null,
                newIdTokenResponse: null,
                log: new LogWithReq(code: $code, detail: $code, req: []),
                response: new ResponseInfo(status: 401, body: 'Unauthorized', headers: []),
            ));

        app()->instance(ShopifyApp::class, $mockShopify);
    };

    // Bind the request the guard inspects, optionally carrying an id_token via
    // the Authorization bearer (XHR) or the document `id_token` query.
    $bindRequest = function (array $query = [], ?string $bearer = null): void {
        $request = Request::create('/', 'GET', $query);

        if ($bearer !== null) {
            $request->headers->set('Authorization', "Bearer {$bearer}");
        }

        app()->instance('request', $request);
    };

    $jwt = function (array $claims): string {
        $segment = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $segment(['alg' => 'HS256', 'typ' => 'JWT']).'.'.$segment($claims).'.'.$segment(['sig']);
    };

    // `invalid_aud` means the signature verified (the secret is right) but the
    // audience did not — a trustworthy, non-spoofable wrong-client_id signal.
    it('throws naming the client id on invalid_aud', function () use ($mockVerify) {
        config(['shopify.client_id' => 'configured-client-id']);
        $mockVerify('invalid_aud');

        expect(fn () => Auth::guard('apphome')->user())
            ->toThrow(ShopifyCredentialMismatchException::class, 'SHOPIFY_CLIENT_ID');
    });

    it('throws on a misconfiguration even with debug off, so it is reported', function () use ($mockVerify) {
        config(['app.debug' => false]);
        $mockVerify('invalid_aud');

        expect(fn () => Auth::guard('apphome')->user())
            ->toThrow(ShopifyCredentialMismatchException::class);
    });

    it('exposes structured reason context without leaking the secret', function () use ($mockVerify) {
        config(['shopify.client_id' => 'configured-client-id']);
        $mockVerify('invalid_aud');

        try {
            Auth::guard('apphome')->user();
            test()->fail('Expected a credential mismatch exception.');
        } catch (ShopifyCredentialMismatchException $e) {
            expect($e->context())->toBe([
                'shopify_reason_code' => 'invalid_aud',
                'configured_client_id' => 'configured-client-id',
            ]);
        }
    });

    // A signature failure (invalid_id_token / patch redirect) is only a wrong
    // secret when the rejected token is still unexpired; a stale or absent token
    // is recoverable and must NOT throw (the middleware returns the package's
    // retry / patch-redirect response instead).
    it('throws naming the client secret when an unexpired bearer token fails signature', function () use ($mockVerify, $bindRequest, $jwt) {
        $bindRequest(bearer: $jwt(['exp' => time() + 300]));
        $mockVerify('invalid_id_token');

        expect(fn () => Auth::guard('apphome')->user())
            ->toThrow(ShopifyCredentialMismatchException::class, 'SHOPIFY_CLIENT_SECRET');
    });

    it('stays recoverable (no throw) for an expired bearer token', function () use ($mockVerify, $bindRequest, $jwt) {
        $bindRequest(bearer: $jwt(['exp' => time() - 300]));
        $mockVerify('invalid_id_token');

        expect(Auth::guard('apphome')->user())->toBeNull();
    });

    it('stays recoverable (no throw) when no token is presented', function () use ($mockVerify, $bindRequest) {
        $bindRequest();
        $mockVerify('invalid_id_token');

        expect(Auth::guard('apphome')->user())->toBeNull();
    });

    // Document loads: a wrong secret surfaces as a patch-id-token redirect with
    // the rejected (unexpired) token still in the query string.
    it('throws naming the client secret when an unexpired document token is rejected', function () use ($mockVerify, $bindRequest, $jwt) {
        $bindRequest(query: ['id_token' => $jwt(['exp' => time() + 300])]);
        $mockVerify('redirect_to_patch_id_token_page');

        expect(fn () => Auth::guard('apphome')->user())
            ->toThrow(ShopifyCredentialMismatchException::class, 'SHOPIFY_CLIENT_SECRET');
    });

    it('stays recoverable on the bootstrap redirect when no document token is present', function () use ($mockVerify, $bindRequest) {
        $bindRequest();
        $mockVerify('redirect_to_patch_id_token_page');

        expect(Auth::guard('apphome')->user())->toBeNull();
    });

    it('stays recoverable for an expired document token', function () use ($mockVerify, $bindRequest, $jwt) {
        $bindRequest(query: ['id_token' => $jwt(['exp' => time() - 300])]);
        $mockVerify('redirect_to_patch_id_token_page');

        expect(Auth::guard('apphome')->user())->toBeNull();
    });

    it('does not treat non-credential failures as a credential mismatch', function () use ($mockVerify, $bindRequest) {
        $bindRequest();
        $mockVerify('invalid_token');

        expect(Auth::guard('apphome')->user())->toBeNull();
    });
});

describe('authenticates existing shop', function () {
    it('authenticates with existing valid access token', function () {
        $shop = Shop::factory()->has(AccessToken::factory()->offline())->create();
        $shopName = $shop->getShopifySubdomain();

        $mockShopify = Mockery::mock(ShopifyApp::class);
        // Mock that the request is valid
        $mockShopify->shouldReceive('verifyAppHomeReq')
            ->once()
            ->andReturn(new ResultWithExchangeableIdToken(
                ok: true,
                shop: $shopName,
                idToken: new IdToken(
                    exchangeable: true,
                    token: 'test_id_token',
                    claims: ['sub' => '12345']
                ),
                userId: '12345',
                newIdTokenResponse: null,
                log: new LogWithReq(
                    code: 'success',
                    detail: 'Token verified',
                    req: []
                ),
                response: new ResponseInfo(
                    status: 200,
                    body: '',
                    headers: []
                )
            ));
        // Mock that the existing access token is valid
        $mockShopify->shouldReceive('refreshTokenExchangedAccessToken')
            ->once()
            ->andReturn(new TokenExchangeResult(
                ok: true,
                shop: $shopName,
                accessToken: null,
                log: new Log(
                    code: 'token_still_valid',
                    detail: 'Access token is still valid. No refresh needed. Proceed with business logic.'
                ),
                httpLogs: [],
                response: new ResponseInfo(
                    status: 200,
                    body: '',
                    headers: (object) []
                )
            ));

        $this->app->instance(ShopifyApp::class, $mockShopify);

        $auth = Auth::guard('apphome')->user();

        expect($auth->is($shop))->toBeTrue();
    });

    it('updates existing access token', function () {
        $shop = Shop::factory()->has(AccessToken::factory()->offline())->create();
        $shopName = $shop->getShopifySubdomain();

        // This is not stored in DB, we just wanna use the factory definitions to create appropriate values
        $fakeAccessToken = AccessToken::factory()->offline()->make();
        $accessToken = [
            'token' => $fakeAccessToken->token,
            'expires_at' => $fakeAccessToken->expires_at,
            'scopes' => $fakeAccessToken->scopes,
            'refresh_token' => $fakeAccessToken->refresh_token,
            'refresh_token_expires_at' => $fakeAccessToken->refresh_token_expires_at,
        ];

        $mockShopify = Mockery::mock(ShopifyApp::class);
        // Mock that the request is valid
        $mockShopify->shouldReceive('verifyAppHomeReq')
            ->once()
            ->andReturn(new ResultWithExchangeableIdToken(
                ok: true,
                shop: $shopName,
                idToken: new IdToken(
                    exchangeable: true,
                    token: 'test_id_token',
                    claims: ['sub' => '12345']
                ),
                userId: '12345',
                newIdTokenResponse: null,
                log: new LogWithReq(
                    code: 'success',
                    detail: 'Token verified',
                    req: []
                ),
                response: new ResponseInfo(
                    status: 200,
                    body: '',
                    headers: []
                )
            ));

        // Mock that a refreshed token is returned
        $mockShopify->shouldReceive('refreshTokenExchangedAccessToken')
            ->once()
            ->andReturn(new TokenExchangeResult(
                ok: true,
                shop: $shopName,
                accessToken: new TokenExchangeAccessToken(
                    accessMode: 'offline',
                    shop: $shopName,
                    token: $accessToken['token'],
                    expires: $accessToken['expires_at']->toIso8601ZuluString(),
                    scope: implode(',', $accessToken['scopes']),
                    refreshToken: $accessToken['refresh_token'],
                    refreshTokenExpires: $accessToken['refresh_token_expires_at']->toIso8601ZuluString(),
                    user: null
                ),
                log: new Log(
                    code: 'success',
                    detail: 'Token refresh successful. Store the new access and refresh token then proceed with business logic.'
                ),
                httpLogs: [],
                response: new ResponseInfo(
                    status: 200,
                    body: '',
                    headers: (object) []
                )
            ));

        $this->app->instance(ShopifyApp::class, $mockShopify);

        $guard = Auth::guard('apphome');

        expect($guard->check())->toBeTrue() // Our "once" assumptions above checks that this check reuses the result from $quard->user()
            ->and($shop)->toBeInstanceOf(Shop::class)
            ->and($shop->shop_domain)->toBe($shopName.'.myshopify.com')
            ->and($shop->accessToken)->toBeInstanceOf(AccessToken::class)
            ->and($shop->accessToken->token)->toBe($accessToken['token'])
            ->and($shop->accessToken->mode)->toBe(AccessTokenMode::Offline)
            ->and($shop->accessToken->scopes)->toEqual($accessToken['scopes'])
            ->and($shop->accessToken->expires_at)->toEqual($accessToken['expires_at'])
            ->and($shop->accessToken->refresh_token)->toBe($accessToken['refresh_token'])
            ->and($shop->accessToken->refresh_token_expires_at)->toEqual($accessToken['refresh_token_expires_at']);
    });

    test('redirects when refresh token is expired', function () {
        $shop = Shop::factory()
            ->has(AccessToken::factory()
                ->offline()
                ->expiredRefreshToken()

            )->create();
        $shopName = $shop->getShopifySubdomain();
    })->skip('Incomplete');
});

describe('authenticates new shop', function () {
    it('creates new Shop and Access Token', function () {
        $shopName = fake()->domainWord();

        // This is not stored in DB, we just wanna use the factory definitions to create appropriate values
        $fakeAccessToken = AccessToken::factory()->offline()->make();
        $accessToken = [
            'token' => $fakeAccessToken->token,
            'expires_at' => $fakeAccessToken->expires_at,
            'scopes' => $fakeAccessToken->scopes,
            'refresh_token' => $fakeAccessToken->refresh_token,
            'refresh_token_expires_at' => $fakeAccessToken->refresh_token_expires_at,
        ];

        $mockShopify = Mockery::mock(ShopifyApp::class);
        // Mock that the request is valid
        $mockShopify->shouldReceive('verifyAppHomeReq')
            ->once()
            ->andReturn(new ResultWithExchangeableIdToken(
                ok: true,
                shop: $shopName,
                idToken: new IdToken(
                    exchangeable: true,
                    token: 'test_id_token',
                    claims: ['sub' => '12345']
                ),
                userId: '12345',
                newIdTokenResponse: null,
                log: new LogWithReq(
                    code: 'success',
                    detail: 'Token verified',
                    req: []
                ),
                response: new ResponseInfo(
                    status: 200,
                    body: '',
                    headers: []
                )
            ));

        // Mock exchangeUsingTokenExchange-request
        $mockShopify->shouldReceive('exchangeUsingTokenExchange')
            ->once()
            ->andReturn(new TokenExchangeResult(
                ok: true,
                shop: $shopName,
                accessToken: new TokenExchangeAccessToken(
                    accessMode: 'offline',
                    shop: $shopName,
                    token: $accessToken['token'],
                    expires: $accessToken['expires_at']->toIso8601ZuluString(),
                    scope: implode(',', $accessToken['scopes']),
                    refreshToken: $accessToken['refresh_token'],
                    refreshTokenExpires: $accessToken['refresh_token_expires_at']->toIso8601ZuluString(),
                    user: null
                ),
                log: new Log(
                    code: 'success',
                    detail: 'Token exchanged'
                ),
                httpLogs: [],
                response: new ResponseInfo(
                    status: 200,
                    body: '',
                    headers: []
                )
            ));

        $this->app->instance(ShopifyApp::class, $mockShopify);

        $guard = Auth::guard('apphome');
        $shop = $guard->user();

        expect($guard->check())->toBeTrue() // Our "once" assumptions above checks that this check reuses the result from $quard->user()
            ->and($shop)->toBeInstanceOf(Shop::class)
            ->and($shop->shop_domain)->toBe($shopName.'.myshopify.com')
            ->and($shop->accessToken)->toBeInstanceOf(AccessToken::class)
            ->and($shop->accessToken->token)->toBe($accessToken['token'])
            ->and($shop->accessToken->mode)->toBe(AccessTokenMode::Offline)
            ->and($shop->accessToken->scopes)->toEqual($accessToken['scopes'])
            ->and($shop->accessToken->expires_at)->toEqual($accessToken['expires_at'])
            ->and($shop->accessToken->refresh_token)->toBe($accessToken['refresh_token'])
            ->and($shop->accessToken->refresh_token_expires_at)->toEqual($accessToken['refresh_token_expires_at']);
    });
});
