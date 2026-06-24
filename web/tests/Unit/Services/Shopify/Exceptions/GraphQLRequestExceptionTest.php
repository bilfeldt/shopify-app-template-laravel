<?php

use App\Services\Shopify\Exceptions\GraphQLRequestException;
use App\Services\Shopify\Exceptions\ShopifyGraphQLExceptionInterface;
use App\Services\Shopify\GraphQL\GraphQLError;
use Shopify\App\Types\GQLResult;
use Shopify\App\Types\Log;
use Shopify\App\Types\ResponseInfo;

function failedGqlResult(string $logCode, string $body = ''): GQLResult
{
    return new GQLResult(
        ok: false,
        shop: 'test-shop.myshopify.com',
        data: null,
        extensions: null,
        log: new Log(code: $logCode, detail: 'Request failed'),
        httpLogs: [],
        response: new ResponseInfo(status: 200, body: $body, headers: []),
    );
}

describe('GraphQLRequestException', function () {
    it('implements ShopifyGraphQLExceptionInterface', function () {
        $exception = GraphQLRequestException::fromResult('shop.myshopify.com', failedGqlResult('network_error'));

        expect($exception)->toBeInstanceOf(ShopifyGraphQLExceptionInterface::class);
    });

    it('stores shop domain and raw result', function () {
        $result = failedGqlResult('network_error');
        $exception = GraphQLRequestException::fromResult('test-shop.myshopify.com', $result);

        expect($exception->getShopDomain())->toBe('test-shop.myshopify.com')
            ->and($exception->getRawResult())->toBe($result);
    });

    it('parses errors from response body when log code is graphql_errors', function () {
        $body = json_encode([
            'errors' => [
                ['message' => 'First error', 'extensions' => ['code' => 'ERROR_1']],
                ['message' => 'Second error', 'extensions' => ['code' => 'ERROR_2']],
            ],
        ]);

        $exception = GraphQLRequestException::fromResult('shop.myshopify.com', failedGqlResult('graphql_errors', $body));
        $errors = $exception->getErrors();

        expect($errors)->toHaveCount(2)
            ->and($errors[0])->toBeInstanceOf(GraphQLError::class)
            ->and($errors[0]->message)->toBe('First error')
            ->and($errors[1]->message)->toBe('Second error')
            ->and($exception->getMessage())->toBe('First error');
    });

    it('returns empty errors for non-graphql_errors codes', function () {
        $exception = GraphQLRequestException::fromResult('shop.myshopify.com', failedGqlResult('unauthorized'));

        expect($exception->getErrors())->toBe([])
            ->and($exception->getMessage())->toBe('Request failed');
    });

    it('exposes log code', function () {
        $exception = GraphQLRequestException::fromResult('shop.myshopify.com', failedGqlResult('rate_limited'));

        expect($exception->getLogCode())->toBe('rate_limited');
    });
});
