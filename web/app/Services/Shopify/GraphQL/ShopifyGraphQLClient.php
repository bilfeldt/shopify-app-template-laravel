<?php

namespace App\Services\Shopify\GraphQL;

use App\Services\Shopify\Exceptions\GraphQLRequestException;
use Closure;
use Illuminate\Support\Facades\Log;
use Shopify\App\ShopifyApp;
use Shopify\App\Types\GQLResult;

/**
 * Thin wrapper around shopify-app-php's adminGraphQLRequest that:
 *  - reactively refreshes the access token on a 401 and retries once
 *  - surfaces GraphQL errors (which the package strips from `data` into
 *    `response->body`) as a thrown {@see GraphQLRequestException}
 *
 * 429 retries with `Retry-After` and 5xx exponential backoff are handled
 * inside the package itself, so no rate limiting lives here.
 */
class ShopifyGraphQLClient
{
    /**
     * @param  Closure():string  $refresher  Returns the new access token string after refreshing.
     */
    public function __construct(
        protected ShopifyApp $shopifyApp,
        protected string $shopDomain,
        protected string $accessToken,
        protected string $apiVersion,
        protected Closure $refresher,
    ) {}

    /**
     * Execute a GraphQL query.
     *
     * @param  array<string, mixed>|null  $variables
     *
     * @throws GraphQLRequestException
     */
    public function query(string $query, ?array $variables = null): ShopifyGraphQLResult
    {
        $result = $this->request($query, $variables);

        if (! $result->ok && $result->log->code === 'unauthorized') {
            Log::info('Access token rejected by Shopify; refreshing and retrying once', [
                'shop_domain' => $this->shopDomain,
            ]);

            $this->accessToken = ($this->refresher)();
            $result = $this->request($query, $variables);
        }

        if (! $result->ok) {
            throw GraphQLRequestException::fromResult($this->shopDomain, $result);
        }

        return new ShopifyGraphQLResult($result);
    }

    /**
     * Execute a GraphQL mutation (semantic alias for query).
     *
     * @param  array<string, mixed>|null  $variables
     *
     * @throws GraphQLRequestException
     */
    public function mutate(string $mutation, ?array $variables = null): ShopifyGraphQLResult
    {
        return $this->query($mutation, $variables);
    }

    public function getShopDomain(): string
    {
        return $this->shopDomain;
    }

    /**
     * @param  array<string, mixed>|null  $variables
     */
    private function request(string $query, ?array $variables): GQLResult
    {
        Log::debug('Executing Shopify GraphQL query', [
            'shop_domain' => $this->shopDomain,
        ]);

        return $this->shopifyApp->adminGraphQLRequest(
            query: $query,
            shop: $this->shopDomain,
            accessToken: $this->accessToken,
            apiVersion: $this->apiVersion,
            variables: $variables,
        );
    }
}
