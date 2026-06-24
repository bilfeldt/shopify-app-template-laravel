<?php

use App\Services\Shopify\Exceptions\ShopifyAuthException;
use App\Services\Shopify\Exceptions\ShopifyAuthTransientException;
use App\Services\Shopify\Exceptions\ShopifyInvalidClientException;
use App\Services\Shopify\Exceptions\ShopifyInvalidGrantException;
use App\Services\Shopify\Exceptions\ShopifyNetworkErrorException;
use App\Services\Shopify\Exceptions\ShopifyReauthRequiredException;
use App\Services\Shopify\Exceptions\ShopifyRefreshTokenExpiredException;
use App\Services\Shopify\Exceptions\ShopifyServerErrorException;
use App\Services\Shopify\Exceptions\ShopifyUnexpectedAuthException;
use Illuminate\Http\Request;
use Shopify\App\Types\Log;
use Shopify\App\Types\ResponseInfo;
use Shopify\App\Types\TokenExchangeResult;

function makeResult(string $code, int $status = 500): TokenExchangeResult
{
    return new TokenExchangeResult(
        ok: false,
        shop: 'example',
        accessToken: null,
        log: new Log(code: $code, detail: "detail for {$code}"),
        httpLogs: [],
        response: new ResponseInfo(status: $status, body: '', headers: (object) []),
    );
}

describe('ShopifyAuthException::fromResult', function () {
    test('maps each known log_code to its concrete exception', function (string $code, string $class) {
        $e = ShopifyAuthException::fromResult(makeResult($code));

        expect($e)->toBeInstanceOf($class)
            ->and($e->result->log->code)->toBe($code)
            ->and($e->getMessage())->toContain($code)
            ->and($e->getMessage())->toContain("detail for {$code}");
    })->with([
        ['refresh_token_expired', ShopifyRefreshTokenExpiredException::class],
        ['invalid_grant', ShopifyInvalidGrantException::class],
        ['invalid_client', ShopifyInvalidClientException::class],
        ['network_error', ShopifyNetworkErrorException::class],
        ['server_error', ShopifyServerErrorException::class],
    ]);

    test('falls back to ShopifyUnexpectedAuthException for unknown codes', function (string $code) {
        $e = ShopifyAuthException::fromResult(makeResult($code));

        expect($e)->toBeInstanceOf(ShopifyUnexpectedAuthException::class);
    })->with([
        'configuration_error',
        'refresh_error',
        'unexpected_error',
        'something_brand_new_in_the_package',
    ]);

    test('context() exposes the package log + response info', function () {
        $e = ShopifyAuthException::fromResult(makeResult('network_error'));

        expect($e->context())->toMatchArray([
            'shop_id' => null,
            'shop_domain' => null,
            'log_code' => 'network_error',
            'log_detail' => 'detail for network_error',
            'response_status' => 500,
        ]);
    });
});

describe('reauth-required exceptions', function () {
    test('render() returns 401 with App Bridge reauthorize headers', function () {
        $e = new ShopifyRefreshTokenExpiredException(makeResult('refresh_token_expired', 401));

        $response = $e->render(Request::create('/'));

        expect($response->getStatusCode())->toBe(401)
            ->and($response->headers->get('X-Shopify-API-Request-Failure-Reauthorize'))->toBe('1')
            ->and($response->headers->get('X-Shopify-API-Request-Failure-Reauthorize-Url'))->not->toBeEmpty();
    });

    test('share the reauth base type for polymorphic catches', function (string $class) {
        $e = new $class(makeResult('refresh_token_expired'));

        expect($e)->toBeInstanceOf(ShopifyReauthRequiredException::class);
    })->with([
        ShopifyRefreshTokenExpiredException::class,
        ShopifyInvalidGrantException::class,
        ShopifyInvalidClientException::class,
    ]);

    test('report() returns false so Laravel skips default reporting', function () {
        $e = new ShopifyInvalidGrantException(makeResult('invalid_grant'));

        expect($e->report())->toBeFalse();
    });
});

describe('transient exceptions', function () {
    test('return null in non-production so APP_DEBUG/Ignition handles them', function () {
        app()->detectEnvironment(fn () => 'local');
        $e = new ShopifyNetworkErrorException(makeResult('network_error'));

        expect($e->render(Request::create('/')))->toBeNull();
    });

    test('return 503 + Retry-After in production', function () {
        app()->detectEnvironment(fn () => 'production');
        $e = new ShopifyServerErrorException(makeResult('server_error'));

        $response = $e->render(Request::create('/'));

        expect($response->getStatusCode())->toBe(503)
            ->and($response->headers->get('Retry-After'))->toBe('30');
    });

    test('share the transient base type for polymorphic catches', function () {
        expect(new ShopifyNetworkErrorException(makeResult('network_error')))
            ->toBeInstanceOf(ShopifyAuthTransientException::class)
            ->and(new ShopifyServerErrorException(makeResult('server_error')))
            ->toBeInstanceOf(ShopifyAuthTransientException::class);
    });
});
