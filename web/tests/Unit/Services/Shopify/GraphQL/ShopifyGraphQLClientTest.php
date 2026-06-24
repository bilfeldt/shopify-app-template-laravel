<?php

use App\Services\Shopify\Exceptions\GraphQLRequestException;
use App\Services\Shopify\GraphQL\ShopifyGraphQLClient;
use App\Services\Shopify\GraphQL\ShopifyGraphQLResult;
use Shopify\App\ShopifyApp;
use Shopify\App\Types\GQLResult;
use Shopify\App\Types\Log;
use Shopify\App\Types\ResponseInfo;

function makeClientResult(
    bool $ok = true,
    ?array $data = [],
    ?array $extensions = null,
    ?string $logCode = 'success',
    ?string $logDetail = '',
    string $body = '',
): GQLResult {
    return new GQLResult(
        ok: $ok,
        shop: 'test-shop.myshopify.com',
        data: $data,
        extensions: $extensions,
        log: new Log(code: $logCode ?? 'success', detail: $logDetail ?? ''),
        httpLogs: [],
        response: new ResponseInfo(status: $ok ? 200 : 401, body: $body, headers: []),
    );
}

function makeClient(ShopifyApp $shopifyApp, ?Closure $refresher = null): ShopifyGraphQLClient
{
    return new ShopifyGraphQLClient(
        shopifyApp: $shopifyApp,
        shopDomain: 'test-shop.myshopify.com',
        accessToken: 'initial-token',
        apiVersion: '2025-01',
        refresher: $refresher ?? fn (): string => 'refreshed-token',
    );
}

describe('ShopifyGraphQLClient', function () {
    it('executes query and returns result', function () {
        $shopifyApp = Mockery::mock(ShopifyApp::class);
        $shopifyApp->shouldReceive('adminGraphQLRequest')
            ->once()
            ->andReturn(makeClientResult(data: ['shop' => ['name' => 'Test Shop']]));

        $result = makeClient($shopifyApp)->query('{ shop { name } }');

        expect($result)->toBeInstanceOf(ShopifyGraphQLResult::class)
            ->and($result->data())->toBe(['shop' => ['name' => 'Test Shop']]);
    });

    it('passes variables through to the package', function () {
        $captured = null;
        $shopifyApp = Mockery::mock(ShopifyApp::class);
        $shopifyApp->shouldReceive('adminGraphQLRequest')
            ->once()
            ->withArgs(function (...$args) use (&$captured) {
                $captured = $args[5] ?? null;

                return true;
            })
            ->andReturn(makeClientResult(data: ['order' => ['id' => '123']]));

        makeClient($shopifyApp)->query('query Q($id: ID!) { order(id: $id) { id } }', ['id' => 'gid://shopify/Order/123']);

        expect($captured)->toBe(['id' => 'gid://shopify/Order/123']);
    });

    it('throws GraphQLRequestException on non-recoverable failure', function () {
        $shopifyApp = Mockery::mock(ShopifyApp::class);
        $shopifyApp->shouldReceive('adminGraphQLRequest')
            ->once()
            ->andReturn(makeClientResult(ok: false, data: null, logCode: 'network_error', logDetail: 'Connection timeout'));

        makeClient($shopifyApp)->query('{ shop { name } }');
    })->throws(GraphQLRequestException::class, 'Connection timeout');

    it('refreshes token and retries once on unauthorized', function () {
        $tokensUsed = [];
        $shopifyApp = Mockery::mock(ShopifyApp::class);
        $shopifyApp->shouldReceive('adminGraphQLRequest')
            ->twice()
            ->withArgs(function (...$args) use (&$tokensUsed) {
                $tokensUsed[] = $args[2] ?? null;

                return true;
            })
            ->andReturnValues([
                makeClientResult(ok: false, data: null, logCode: 'unauthorized', logDetail: 'Token invalid'),
                makeClientResult(data: ['shop' => ['name' => 'Test Shop']]),
            ]);

        $refresherCalled = false;
        $client = makeClient($shopifyApp, function () use (&$refresherCalled): string {
            $refresherCalled = true;

            return 'refreshed-token';
        });

        $result = $client->query('{ shop { name } }');

        expect($refresherCalled)->toBeTrue()
            ->and($tokensUsed)->toBe(['initial-token', 'refreshed-token'])
            ->and($result->data())->toBe(['shop' => ['name' => 'Test Shop']]);
    });

    it('throws if still unauthorized after refresh', function () {
        $shopifyApp = Mockery::mock(ShopifyApp::class);
        $shopifyApp->shouldReceive('adminGraphQLRequest')
            ->twice()
            ->andReturn(makeClientResult(ok: false, data: null, logCode: 'unauthorized', logDetail: 'Token invalid'));

        makeClient($shopifyApp)->query('{ shop { name } }');
    })->throws(GraphQLRequestException::class);

    it('mutate is an alias for query', function () {
        $shopifyApp = Mockery::mock(ShopifyApp::class);
        $shopifyApp->shouldReceive('adminGraphQLRequest')
            ->once()
            ->andReturn(makeClientResult(data: ['productCreate' => ['product' => ['id' => '123']]]));

        $result = makeClient($shopifyApp)->mutate('mutation { productCreate(input: {}) { product { id } } }');

        expect($result)->toBeInstanceOf(ShopifyGraphQLResult::class);
    });

    it('returns shop domain', function () {
        $client = makeClient(Mockery::mock(ShopifyApp::class));

        expect($client->getShopDomain())->toBe('test-shop.myshopify.com');
    });
});
