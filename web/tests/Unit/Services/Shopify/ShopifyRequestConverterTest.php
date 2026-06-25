<?php

use App\Services\Shopify\ShopifyRequestConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shopify\App\Types\LogWithReq;
use Shopify\App\Types\ResponseInfo;

pest()->group('services', 'shopify');

describe('toShopifyRequest', function () {
    it('converts a Laravel request to Shopify request format', function () {
        $request = Request::create(
            uri: 'https://example.com/test?foo=bar',
            method: 'POST',
            content: '{"data": "test"}',
        );
        $request->headers->set('Authorization', 'Bearer token123');
        $request->headers->set('Content-Type', 'application/json');

        $result = ShopifyRequestConverter::toShopifyRequest($request);

        expect($result)->toBeArray()
            ->and($result['method'])->toBe('POST')
            ->and($result['url'])->toBe('https://example.com/test?foo=bar')
            ->and($result['body'])->toBe('{"data": "test"}')
            ->and($result['headers'])->toBeArray();
    });

    it('includes all headers as single string values', function () {
        // shopify-app-php expects one string per header (e.g. when comparing
        // the X-Shopify-Hmac-SHA256 header), not Laravel's array of values.
        $request = Request::create(
            uri: 'https://example.com/test',
            method: 'GET',
        );
        $request->headers->set('X-Custom-Header', 'custom-value');

        $result = ShopifyRequestConverter::toShopifyRequest($request);

        expect($result['headers']['x-custom-header'])->toBe('custom-value');
    });

    it('joins multi-value headers with a comma', function () {
        $request = Request::create(
            uri: 'https://example.com/test',
            method: 'GET',
        );
        $request->headers->set('X-Multi-Header', ['first', 'second']);

        $result = ShopifyRequestConverter::toShopifyRequest($request);

        expect($result['headers']['x-multi-header'])->toBe('first, second');
    });

    it('handles GET requests with empty body', function () {
        $request = Request::create(
            uri: 'https://example.com/path',
            method: 'GET',
        );

        $result = ShopifyRequestConverter::toShopifyRequest($request);

        expect($result['method'])->toBe('GET')
            ->and($result['body'])->toBe('');
    });

    it('includes the full URL with query parameters', function () {
        $request = Request::create(
            uri: 'https://example.com/auth?shop=test.myshopify.com&code=abc123',
            method: 'GET',
        );

        $result = ShopifyRequestConverter::toShopifyRequest($request);

        // Query parameters may be reordered, so just check they're present
        expect($result['url'])->toContain('https://example.com/auth')
            ->and($result['url'])->toContain('shop=test.myshopify.com')
            ->and($result['url'])->toContain('code=abc123');
    });
});

describe('toResponse', function () {
    it('converts ResponseInfo to Laravel Response', function () {
        $responseInfo = new ResponseInfo(
            status: 200,
            body: '<html>Test</html>',
            headers: ['Content-Type' => 'text/html'],
        );
        $log = new LogWithReq(
            code: 'success',
            detail: 'Request successful',
            req: [],
        );

        Log::spy();

        $response = ShopifyRequestConverter::toResponse($responseInfo, $log);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('<html>Test</html>')
            ->and($response->headers->get('Content-Type'))->toBe('text/html');
    });

    it('handles multiple headers', function () {
        $responseInfo = new ResponseInfo(
            status: 200,
            body: 'test',
            headers: [
                'Content-Type' => 'text/plain',
                'X-Custom' => 'value',
                'X-Another' => 'another-value',
            ],
        );
        $log = new LogWithReq(code: 'test', detail: 'test', req: []);

        Log::spy();

        $response = ShopifyRequestConverter::toResponse($responseInfo, $log);

        expect($response->headers->get('Content-Type'))->toBe('text/plain')
            ->and($response->headers->get('X-Custom'))->toBe('value')
            ->and($response->headers->get('X-Another'))->toBe('another-value');
    });

    it('handles object headers', function () {
        $responseInfo = new ResponseInfo(
            status: 400,
            body: 'Bad Request',
            headers: (object) ['X-Error' => 'true'],
        );
        $log = new LogWithReq(code: 'error', detail: 'Bad request', req: []);

        Log::spy();

        $response = ShopifyRequestConverter::toResponse($responseInfo, $log);

        expect($response->getStatusCode())->toBe(400)
            ->and($response->headers->get('X-Error'))->toBe('true');
    });

    it('handles empty headers object', function () {
        $responseInfo = new ResponseInfo(
            status: 500,
            body: 'Internal Server Error',
            headers: (object) [],
        );
        $log = new LogWithReq(code: 'error', detail: 'Server error', req: []);

        Log::spy();

        $response = ShopifyRequestConverter::toResponse($responseInfo, $log);

        expect($response->getStatusCode())->toBe(500)
            ->and($response->getContent())->toBe('Internal Server Error');
    });

    it('logs the conversion', function () {
        Log::spy();

        $responseInfo = new ResponseInfo(
            status: 200,
            body: 'test',
            headers: [],
        );
        $log = new LogWithReq(
            code: 'test_code',
            detail: 'Test detail message',
            req: [],
        );

        ShopifyRequestConverter::toResponse($responseInfo, $log);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Converting Shopify ResponseInfo to Response', [
                'code' => 'test_code',
                'detail' => 'Test detail message',
            ]);
    });

    it('returns different status codes correctly', function (int $status) {
        $responseInfo = new ResponseInfo(
            status: $status,
            body: 'test',
            headers: [],
        );
        $log = new LogWithReq(code: 'test', detail: 'test', req: []);

        Log::spy();

        $response = ShopifyRequestConverter::toResponse($responseInfo, $log);

        expect($response->getStatusCode())->toBe($status);
    })->with([200, 201, 400, 401, 403, 404, 500]);
});
