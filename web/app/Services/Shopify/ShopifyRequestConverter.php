<?php

namespace App\Services\Shopify;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Shopify\App\Types\LogWithReq;
use Shopify\App\Types\ResponseInfo;

class ShopifyRequestConverter
{
    /**
     * Convert a Laravel Request to the format expected by shopify-app-php.
     *
     * Laravel exposes each header as an array of values, but shopify-app-php
     * expects a single string value per header (e.g. when comparing the
     * X-Shopify-Hmac-SHA256 header), so multi-value headers are joined with a
     * comma per RFC 9110.
     *
     * @return array{method: string, headers: array<string, string>, url: string, body: string}
     */
    public static function toShopifyRequest(Request $request): array
    {
        $headers = array_map(
            fn (array $values): string => implode(', ', $values),
            $request->headers->all(),
        );

        return [
            'method' => $request->method(),
            'headers' => $headers,
            'url' => $request->fullUrl(),
            'body' => $request->getContent(),
        ];
    }

    /**
     * Convert a shopify-app-php result to a Laravel Response.
     */
    public static function toResponse(
        ResponseInfo $responseInfo,
        LogWithReq $log
    ): Response {
        Log::info('Converting Shopify ResponseInfo to Response', [
            'code' => $log->code,
            'detail' => $log->detail,
        ]);

        $headers = is_array($responseInfo->headers) ? $responseInfo->headers : (array) $responseInfo->headers;

        return response($responseInfo->body, $responseInfo->status)
            ->withHeaders($headers);
    }
}
