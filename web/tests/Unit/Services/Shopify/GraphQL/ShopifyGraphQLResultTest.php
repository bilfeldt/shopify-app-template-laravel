<?php

use App\Services\Shopify\GraphQL\ShopifyGraphQLResult;
use App\Services\Shopify\GraphQL\ThrottleStatus;
use Shopify\App\Types\GQLResult;
use Shopify\App\Types\Log;
use Shopify\App\Types\ResponseInfo;

function makeGqlResult(?array $data = [], ?array $extensions = null): GQLResult
{
    return new GQLResult(
        ok: true,
        shop: 'test-shop.myshopify.com',
        data: $data,
        extensions: $extensions,
        log: new Log(code: 'success', detail: ''),
        httpLogs: [],
        response: new ResponseInfo(status: 200, body: '', headers: []),
    );
}

describe('ShopifyGraphQLResult', function () {
    it('returns data from result', function () {
        $result = new ShopifyGraphQLResult(makeGqlResult(data: ['shop' => ['name' => 'Test Shop']]));

        expect($result->data())->toBe(['shop' => ['name' => 'Test Shop']]);
    });

    it('returns empty array when data is null', function () {
        $result = new ShopifyGraphQLResult(makeGqlResult(data: null));

        expect($result->data())->toBe([]);
    });

    it('parses throttle status from extensions', function () {
        $result = new ShopifyGraphQLResult(makeGqlResult(extensions: [
            'cost' => [
                'throttleStatus' => [
                    'maximumAvailable' => 2000,
                    'currentlyAvailable' => 1500,
                    'restoreRate' => 100.0,
                ],
            ],
        ]));

        $throttle = $result->throttleStatus();

        expect($throttle)->toBeInstanceOf(ThrottleStatus::class)
            ->and($throttle->maximumAvailable)->toBe(2000)
            ->and($throttle->currentlyAvailable)->toBe(1500);
    });

    it('returns null throttle status when not present', function () {
        $result = new ShopifyGraphQLResult(makeGqlResult());

        expect($result->throttleStatus())->toBeNull();
    });

    it('provides access to raw result', function () {
        $gqlResult = makeGqlResult();

        $result = new ShopifyGraphQLResult($gqlResult);

        expect($result->raw())->toBe($gqlResult);
    });
});
