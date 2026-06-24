<?php

use App\Services\Shopify\Exceptions\ShopifyCredentialMismatchException;
use Shopify\App\ShopifyApp;
use Shopify\App\Types\LogWithReq;
use Shopify\App\Types\ResponseInfo;
use Shopify\App\Types\ResultWithExchangeableIdToken;

/**
 * The middleware must follow Shopify's recommended flow: on a recoverable
 * verification failure it returns the package's own response (so App Bridge can
 * refresh the session token) instead of a generic 401, while a genuine
 * credential misconfiguration throws and is reported as a 500.
 */
function mockVerifyAppHomeReq(string $code, int $status, array $headers = [], string $body = ''): void
{
    $mockShopify = Mockery::mock(ShopifyApp::class);
    $mockShopify->shouldReceive('verifyAppHomeReq')
        ->once()
        ->andReturn(new ResultWithExchangeableIdToken(
            ok: false,
            shop: null,
            idToken: null,
            userId: null,
            newIdTokenResponse: null,
            log: new LogWithReq(code: $code, detail: $code, req: []),
            response: new ResponseInfo(status: $status, body: $body, headers: $headers),
        ));

    app()->instance(ShopifyApp::class, $mockShopify);
}

function unexpiredIdToken(): string
{
    $segment = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

    return $segment(['alg' => 'HS256', 'typ' => 'JWT']).'.'.$segment(['exp' => time() + 300]).'.'.$segment(['sig']);
}

it('emits the package 302 patch-id-token redirect on a recoverable failure', function () {
    mockVerifyAppHomeReq(
        code: 'redirect_to_patch_id_token_page',
        status: 302,
        headers: ['Location' => 'https://example.test/auth/patch-id-token?shopify-reload=%2Fconnect'],
    );

    $this->get('/')
        ->assertStatus(302)
        ->assertHeader('Location', 'https://example.test/auth/patch-id-token?shopify-reload=%2Fconnect');
});

it('emits the package 401 retry response for a stale session token', function () {
    // No id_token on the request → recoverable: the package response (a 401 with
    // the retry header) is returned so App Bridge fetches a fresh token.
    mockVerifyAppHomeReq(
        code: 'invalid_id_token',
        status: 401,
        headers: ['X-Shopify-Retry-Invalid-Session-Request' => '1'],
        body: 'Unauthorized',
    );

    $this->get('/')
        ->assertStatus(401)
        ->assertHeader('X-Shopify-Retry-Invalid-Session-Request', '1');
});

it('throws a reported credential mismatch on invalid_aud', function () {
    $this->withoutExceptionHandling();

    mockVerifyAppHomeReq(code: 'invalid_aud', status: 401);

    expect(fn () => $this->get('/'))
        ->toThrow(ShopifyCredentialMismatchException::class);
});

it('throws a reported credential mismatch when an unexpired document token is rejected', function () {
    $this->withoutExceptionHandling();

    mockVerifyAppHomeReq(code: 'redirect_to_patch_id_token_page', status: 302);

    expect(fn () => $this->get('/?id_token='.unexpiredIdToken()))
        ->toThrow(ShopifyCredentialMismatchException::class);
});

it('renders a generic 500 with a reference for a misconfiguration in production', function () {
    config(['app.debug' => false]);

    mockVerifyAppHomeReq(code: 'invalid_aud', status: 401);

    $this->get('/')
        ->assertStatus(500)
        ->assertSee('Something went wrong')
        ->assertDontSee('invalid_aud');
});
