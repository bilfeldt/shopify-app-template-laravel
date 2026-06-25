<?php

namespace App\Services\Shopify;

use App\Actions\RefreshAccessTokenAction;
use App\Models\AccessToken;
use App\Models\Shop;
use App\Services\Shopify\Exceptions\AccessTokenStillValidException;
use App\Services\Shopify\Exceptions\MissingAccessTokenException;
use App\Services\Shopify\Exceptions\MissingApiVersionException;
use App\Services\Shopify\Exceptions\ShopifyAuthException;
use App\Services\Shopify\GraphQL\ShopifyGraphQLClient;
use Illuminate\Support\Facades\Log;
use Shopify\App\ShopifyApp;
use Shopify\App\Types\TokenExchangeAccessToken;

/**
 * Single entry point for Shopify operations on behalf of a shop, scoped to its
 * persisted offline access token.
 *
 * Always obtained via `$shop->shopify()` (or the {@see ShopifyShopFactory}).
 * Background callers (queue jobs, webhook handlers, console commands) use this
 * for GraphQL; request-time callers may also use it, but online tokens are
 * never persisted — see {@see AccessToken}.
 */
class ShopifyShop
{
    /**
     * How close to expiry (in minutes) an offline token may be before we
     * proactively refresh it. Kept small on purpose: offline tokens live only
     * ~60 minutes, and the GraphQL client also refreshes reactively on a 401,
     * so this buffer only needs to absorb request latency and clock skew — not
     * pre-empt the whole token lifetime (which would mean refreshing on nearly
     * every call).
     */
    private const ACCESS_TOKEN_REFRESH_BUFFER_MINUTES = 2;

    private ?ShopifyGraphQLClient $client = null;

    public function __construct(
        protected Shop $shop,
        protected ShopifyApp $shopifyApp,
    ) {}

    /**
     * Details about the shop itself (name, currency, plan, contact).
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        $query = <<<'GRAPHQL'
            query GetShopDetails {
                shop {
                    id
                    name
                    myshopifyDomain
                    currencyCode
                    plan {
                        publicDisplayName
                    }
                    contactEmail
                }
            }
            GRAPHQL;

        return $this->graphql()->query($query)->data()['shop'] ?? [];
    }

    /**
     * GraphQL client for this shop. Proactively refreshes the access token if
     * it is near expiry, and the returned client will reactively refresh on a
     * 401 from Shopify and retry once.
     */
    public function graphql(): ShopifyGraphQLClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if ($this->shouldAccessTokenBeRefreshed()) {
            Log::debug('Proactively refreshing access token before issuing GraphQL client', [
                'shop_id' => $this->shop->id,
            ]);
            app(RefreshAccessTokenAction::class)->execute($this->shop);
        }

        $accessToken = $this->shop->accessToken;

        if (! $accessToken) {
            throw new MissingAccessTokenException(shopDomain: $this->shop->getShopifySubdomain());
        }

        $apiVersion = config('shopify.api_version');

        if (blank($apiVersion)) {
            throw new MissingApiVersionException(shopDomain: $this->shop->getShopifySubdomain());
        }

        return $this->client = new ShopifyGraphQLClient(
            shopifyApp: $this->shopifyApp,
            shopDomain: $this->shop->getShopifySubdomain(),
            accessToken: $accessToken->token,
            apiVersion: $apiVersion,
            refresher: function (): string {
                app(RefreshAccessTokenAction::class)->execute($this->shop);

                return $this->shop->accessToken->token;
            },
        );
    }

    /**
     * Check if the shop has a valid (non-expired) access token.
     */
    public function isAccessTokenValid(): bool
    {
        $accessToken = $this->shop->accessToken;

        if (! $accessToken) {
            return false;
        }

        if (! $accessToken->expires_at) {
            return true;
        }

        return $accessToken->expires_at->isFuture();
    }

    /**
     * Check if the access token should be proactively refreshed.
     *
     * Returns true if the token is expiring soon (within the threshold)
     * or if the refresh token is about to expire.
     */
    public function shouldAccessTokenBeRefreshed(int $thresholdMinutes = self::ACCESS_TOKEN_REFRESH_BUFFER_MINUTES): bool
    {
        $accessToken = $this->shop->accessToken;

        if (! $accessToken) {
            return false;
        }

        if (! $accessToken->expires_at) {
            return false;
        }

        if ($accessToken->expires_at->isPast()) {
            return true;
        }

        if (now()->diffInMinutes($accessToken->expires_at, absolute: true) <= $thresholdMinutes) {
            return true;
        }

        if ($accessToken->refresh_token_expires_at && now()->diffInMinutes($accessToken->refresh_token_expires_at, absolute: true) <= $thresholdMinutes) {
            return true;
        }

        return false;
    }

    /**
     * Refresh the shop's access token via the Shopify API.
     *
     * @throws MissingAccessTokenException
     * @throws ShopifyAuthException
     * @throws AccessTokenStillValidException
     */
    public function refreshAccessToken(): TokenExchangeAccessToken
    {
        $accessToken = $this->shop->accessToken;

        if (! $accessToken) {
            throw new MissingAccessTokenException(shopDomain: $this->shop->getShopifySubdomain());
        }

        Log::debug('Attempting to refresh Shopify access token', [
            'shop_id' => $this->shop->id,
            'access_token_id' => $accessToken->id,
        ]);

        // The shopify-app-php package appends `.myshopify.com` when building the
        // OAuth token endpoint URL, so we must pass the subdomain only.
        $refreshResult = $this->shopifyApp->refreshTokenExchangedAccessToken([
            'accessMode' => 'offline',
            'shop' => $this->shop->getShopifySubdomain(),
            'token' => $accessToken->token,
            'expires' => $accessToken->expires_at?->format('Y-m-d\TH:i:s\Z'),
            'scope' => implode(',', $accessToken->scopes),
            'refreshToken' => $accessToken->refresh_token,
            'refreshTokenExpires' => $accessToken->refresh_token_expires_at?->format('Y-m-d\TH:i:s\Z'),
            'user' => null,
        ]);

        if (! $refreshResult->ok) {
            Log::error('Failed to refresh Shopify access token', [
                'shop_id' => $this->shop->id,
                'access_token_id' => $accessToken->id,
                'log_code' => $refreshResult->log?->code,
                'log_detail' => $refreshResult->log?->detail,
                'response_status' => $refreshResult->response->status,
                'response_body' => $refreshResult->response->body,
            ]);

            throw ShopifyAuthException::fromResult($refreshResult, $this->shop);
        }

        if (! $refreshResult->accessToken) {
            Log::debug('Access token is still valid, no refresh needed', [
                'shop_id' => $this->shop->id,
                'access_token_id' => $accessToken->id,
            ]);

            throw new AccessTokenStillValidException;
        }

        Log::info('Successfully refreshed Shopify access token', [
            'shop_id' => $this->shop->id,
            'access_token_id' => $accessToken->id,
        ]);

        return $refreshResult->accessToken;
    }
}
