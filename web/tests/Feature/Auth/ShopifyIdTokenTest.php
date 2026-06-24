<?php

use Shopify\App\ShopifyApp;
use Shopify\App\Types\AppHomePatchIdTokenResult;
use Shopify\App\Types\LogWithReq;
use Shopify\App\Types\ResponseInfo;

describe('Patch Id Token', function () {
    it('route exists by name', function () {
        $route = route('auth.patch-id-token', absolute: false);

        expect($route)->toBe('/auth/patch-id-token');
    });

    it('fetches a fresh token', function () {
        $mockShopify = Mockery::mock(ShopifyApp::class);
        $mockShopify->shouldReceive('appHomePatchIdToken')
            ->once()
            ->withArgs(function ($req) {
                // Verify the request format matches what ShopifyApp expects
                return isset($req['method'])
                    && isset($req['headers'])
                    && isset($req['url'])
                    && isset($req['body'])
                    && $req['method'] === 'GET'
                    && str_contains($req['url'], '/auth/patch-id-token');
            })
            ->andReturn(new AppHomePatchIdTokenResult(
                ok: true,
                shop: 'test-shop',
                log: new LogWithReq(
                    code: 'success',
                    detail: 'ID token patched successfully',
                    req: []
                ),
                response: new ResponseInfo(
                    status: 200,
                    body: '{"patched": true}',
                    headers: ['Content-Type' => 'application/json']
                )
            ));

        $this->app->instance(ShopifyApp::class, $mockShopify);

        $response = $this->get('/auth/patch-id-token');

        $response
            ->assertStatus(200)
            ->assertJson(['patched' => true]);
    });

    it('handles invalid token response', function () {
        $mockShopify = Mockery::mock(ShopifyApp::class);
        $mockShopify->shouldReceive('appHomePatchIdToken')
            ->once()
            ->andReturn(new AppHomePatchIdTokenResult(
                ok: false,
                shop: null,
                log: new LogWithReq(
                    code: 'invalid_token',
                    detail: 'The ID token is invalid',
                    req: []
                ),
                response: new ResponseInfo(
                    status: 401,
                    body: 'Invalid token',
                    headers: []
                )
            ));

        $this->app->instance(ShopifyApp::class, $mockShopify);

        $response = $this->get('/auth/patch-id-token');

        $response
            ->assertStatus(401)
            ->assertContent('Invalid token');
    });
});
