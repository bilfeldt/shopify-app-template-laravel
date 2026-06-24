<?php

namespace App\Auth\Guards;

use App\Actions\Auth\CreateShopAction;
use App\Actions\Auth\SaveTokenExchangeAccessTokenAction;
use App\Events\ShopifyAccessTokenRefreshed;
use App\Http\Middleware\VerifyShopifyAppHome;
use App\Models\AccessToken;
use App\Models\Shop;
use App\Services\Shopify\Exceptions\ShopifyAuthException;
use App\Services\Shopify\Exceptions\ShopifyCredentialMismatchException;
use App\Services\Shopify\ShopifyRequestConverter;
use App\Services\Shopify\ShopUtil;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shopify\App\ShopifyApp;
use Shopify\App\Types\ResultWithExchangeableIdToken;
use Shopify\App\Types\TokenExchangeResult;

/**
 * Guard for authenticating Shopify embedded app requests.
 *
 * This guard can be used to verify requests from shopify that contain
 * an exchangeable ID token. It handles token exchange to refresh access tokens.
 *
 * Works with:
 * - App home
 * - Admin UI Extensions
 * - POS UI Extensions
 *
 * Does not work with (requests without exchangeable id tokens):
 * - Webhooks
 * - App Proxy
 * - Customer Account UI Extension
 * - Checkout UI Extension
 *
 * @see https://github.com/Shopify/shopify-app-php/tree/main?tab=readme-ov-file#verifying-requests-with-exchangeable-id-tokens
 * @see https://github.com/Shopify/shopify-app-php/tree/main?tab=readme-ov-file#verifying-requests-without-exchangeable-id-tokens
 * @see https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens/token-exchange
 * @see https://shopify.dev/docs/apps/build/authentication-authorization/set-embedded-app-authorization?extension=javascript#session-token-in-the-request-header
 */
class ShopifyAppHomeGuard implements Guard
{
    public const DRIVER_NAME = 'shopify-app-home';

    protected ?Authenticatable $user = null;

    protected ?ResultWithExchangeableIdToken $verificationResult = null;

    protected bool $credentialMismatchChecked = false;

    public function __construct(
        protected UserProvider $provider,
        protected ShopifyApp $shopify,
        protected Request $request
    ) {}

    /**
     * Determine if the current request is authenticated.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Determine if the current request is a guest.
     */
    public function guest(): bool
    {
        return ! $this->check();
    }

    /**
     * Get the currently authenticated shop.
     */
    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        Log::debug('Authenticating via Shopify App Home Guard');

        $result = $this->verify();
        $shopSubdomain = $result->shop;

        if (! $result->ok) {
            Log::warning('Invalid Shopify App Home authentication', [
                'shop_subdomain' => $shopSubdomain,
                'shopify_user_id' => $result->userId,
                'log_code' => $result->log->code,
                'log_detail' => $result->log->detail,
            ]);

            return null;
        }

        Log::debug('Shopify request is valid', [
            'shop_subdomain' => $shopSubdomain,
        ]);

        $shop = $this->findOrCreateShop($result);
        $this->handleAccessToken($shop);

        Log::debug('Authenticated shop via Shopify App Home guard', [
            'shop_subdomain' => $shopSubdomain,
            'shop_id' => $shop->id,
        ]);

        $this->user = $shop;
        $this->storeResultAsRequestAttributes($result);

        return $this->user;
    }

    /**
     * Get the ID for the currently authenticated shop.
     */
    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * Validate a shop's credentials (not used for token-based auth).
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    /**
     * Determine if the guard has a user instance.
     */
    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    /**
     * Set the current user.
     */
    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Verify the request, failing hard on a credential misconfiguration.
     *
     * Returns the raw verification result so the caller (the
     * {@see VerifyShopifyAppHome} middleware) can emit the
     * package's own response on recoverable failures — a 302 to the
     * patch-id-token page, or a 401 carrying the retry header — exactly as
     * Shopify recommends. A genuine wrong-credentials fingerprint instead throws
     * {@see ShopifyCredentialMismatchException}: no session-token refresh can
     * recover from it, so it must surface as a reported 500 rather than loop.
     */
    public function verify(): ResultWithExchangeableIdToken
    {
        $result = $this->verifyRequest();

        if (! $result->ok && ! $this->credentialMismatchChecked) {
            $this->credentialMismatchChecked = true;
            $this->reportCredentialMismatch($result);
        }

        return $result;
    }

    /**
     * Verify the Shopify request.
     */
    protected function verifyRequest(): ResultWithExchangeableIdToken
    {
        if ($this->verificationResult !== null) {
            return $this->verificationResult;
        }

        $shopifyRequest = ShopifyRequestConverter::toShopifyRequest($this->request);

        $this->verificationResult = $this->shopify->verifyAppHomeReq(
            $shopifyRequest,
            appHomePatchIdTokenPath: config('shopify.patch_id_token_path'),
        );

        return $this->verificationResult;
    }

    /**
     * Surface a credential misconfiguration as a clear, actionable error.
     *
     * When Shopify rejects the request in a way that fingerprints a wrong
     * `SHOPIFY_CLIENT_ID` or `SHOPIFY_CLIENT_SECRET`, throw instead of returning
     * null (which renders as a blank embedded page or an opaque 401). This is a
     * "cannot happen" state — a deploy shipped mismatched credentials — so we
     * fail loudly and let it be reported. The framework decides what the
     * merchant sees: the full message under `APP_DEBUG`, a generic 500 in
     * production. Only the precise credential-failure fingerprints throw;
     * ordinary missing/expired tokens return without throwing so the middleware
     * can emit the package's recovery response (the 302 patch redirect / 401 retry).
     */
    protected function reportCredentialMismatch(ResultWithExchangeableIdToken $result): void
    {
        $clientId = (string) config('shopify.client_id');
        $code = $result->log->code;

        // `invalid_aud` is the one fully trustworthy fingerprint: the package
        // verified the signature with our secret (so the secret is right) and
        // then found the token's `aud` != our client_id. A forged token cannot
        // pass the signature check, so a wrong SHOPIFY_CLIENT_ID throws directly.
        if ($code === 'invalid_aud') {
            throw ShopifyCredentialMismatchException::fromVerification(
                'invalid_aud',
                $result->log->detail,
                $clientId,
            );
        }

        // A signature failure is reported as `invalid_id_token` on the bearer
        // (XHR) path and as a patch-id-token redirect on the document path. Both
        // are ambiguous — a wrong SHOPIFY_CLIENT_SECRET looks identical to a
        // merely stale token — so inspect the presented token to tell them apart
        // instead of throwing (which would 500 a recoverable session refresh).
        if ($code === 'invalid_id_token' || $code === 'redirect_to_patch_id_token_page') {
            $this->reportRejectedSecret($clientId);
        }
    }

    /**
     * Decide whether a signature-rejected id_token indicates a wrong secret.
     *
     * The token (from the Authorization bearer or the document `id_token` query)
     * is decoded WITHOUT verifying its signature, purely to read `exp`. An
     * unexpired token that still failed verification cannot be recovered by
     * minting a fresh one — its signature does not match our configured secret —
     * so that is a wrong SHOPIFY_CLIENT_SECRET. A stale (expired) or absent token
     * is recoverable and left to the package's patch/retry flow.
     *
     * NOTE: a failed-signature token is untrusted, so its claims are
     * attacker-controllable. The worst an attacker can do here is trigger this
     * reported 500 (no access is ever granted); rely on the error tracker's
     * grouping/rate-limiting to absorb any such noise.
     */
    protected function reportRejectedSecret(string $clientId): void
    {
        $idToken = $this->presentedIdToken();

        if ($idToken === null) {
            return; // Nothing to inspect — e.g. the normal first-hop bootstrap.
        }

        $claims = $this->decodeIdTokenClaims($idToken);

        if ($claims === null) {
            return; // Malformed — treat as a recoverable/garbage token.
        }

        $exp = $claims['exp'] ?? null;

        if (is_numeric($exp) && (int) $exp > time()) {
            throw ShopifyCredentialMismatchException::fromVerification(
                'invalid_id_token',
                'An unexpired ID token failed signature verification against the configured client secret.',
                $clientId,
            );
        }
    }

    /**
     * The id_token presented on the request, from the Authorization bearer
     * (XHR/fetch) or, failing that, the document `id_token` query parameter.
     */
    private function presentedIdToken(): ?string
    {
        $authorization = (string) $this->request->header('Authorization', '');

        if (str_starts_with($authorization, 'Bearer ')) {
            $token = substr($authorization, 7);

            return $token !== '' ? $token : null;
        }

        $queryToken = $this->request->query('id_token');

        return is_string($queryToken) && $queryToken !== '' ? $queryToken : null;
    }

    /**
     * Base64url-decode a JWT's payload segment without verifying the signature.
     *
     * @return array<string, mixed>|null
     */
    private function decodeIdTokenClaims(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        $base64 = strtr($parts[1], '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);

        $payload = base64_decode($base64, true);

        if ($payload === false) {
            return null;
        }

        $claims = json_decode($payload, true);

        return is_array($claims) ? $claims : null;
    }

    /**
     * Find or create the shop and handle token exchange.
     */
    protected function findOrCreateShop(ResultWithExchangeableIdToken $result): Shop
    {
        $shopDomain = ShopUtil::getDomainFromSubdomain($result->shop);

        $shop = $this->provider->retrieveById($shopDomain);

        if (! $shop) {
            Log::info('No existing Shop-model found', [
                'shop_domain' => $shopDomain,
            ]);

            $shop = resolve(CreateShopAction::class)->execute($shopDomain);
        } else {
            Log::debug('Existing Shop model found', [
                'shop_id' => $shop->id,
            ]);
        }

        if ($shop->shop_domain !== $shopDomain) {
            // This should not be possible.
            throw new \LogicException('Shop domain mismatch');
        }

        return $shop;
    }

    /**
     * Make sure that the access token we have is valid, refresh it if needed and create a new one if we do not
     * already have one.
     */
    protected function handleAccessToken(Shop $shop): void
    {
        if (! $shop->accessToken) {
            Log::info('No existing AccessToken-model found', [
                'shop_id' => $shop->id,
            ]);

            $this->createAndSaveNewAccessToken($shop);

            return;
        }

        Log::debug('Checking if existing AccessToken-model needs refreshing', [
            'shop_id' => $shop->id,
        ]);
        // refresh_token_expired: If there is a refresh token and that is expired, then it returns a 401 and the user must re-authenticate the app.
        // token_still_valid: A simple OK TokenExchangeResult if the existing access token is still valid (the accessToken-parameter is null).
        // configuration_error: 5xx response representing incorrect configuration of the app.
        // server_error: 500 response when the package was not able to refresh the token.
        // success: An OK TokenExchangeResult with a fresh accessToken-parameter is returned)
        // NOTE: This does NOT verify that the correct scopes are present on the existing access token.
        $refreshResult = $this->shopify->refreshTokenExchangedAccessToken(
            $this->toTokenExchangeAccessTokenArray($shop->getShopifySubdomain(), $shop->accessToken)
        );

        if (! $refreshResult->ok) {
            Log::error('Failed to refresh existing Shopify Access Token', [
                'shop_id' => $shop->id,
                'access_token_id' => $shop->accessToken->id,
                'log_code' => $refreshResult->log?->code,
                'log_detail' => $refreshResult->log?->detail,
                'response_status' => $refreshResult->response->status,
                'response_body' => $refreshResult->response->body,
            ]);

            throw ShopifyAuthException::fromResult($refreshResult, $shop);
        }

        // If the token was refreshed, then we need to handle the new access token
        if ($refreshResult->accessToken) {
            // A new access token was returned, update the shop record
            Log::info('Refreshed Shopify Access Token', [
                'shop_id' => $shop->id,
                'access_token_id' => $shop->accessToken->id,
                'log_code' => $refreshResult->log?->code,
                'log_detail' => $refreshResult->log?->detail,
            ]);

            resolve(SaveTokenExchangeAccessTokenAction::class)->execute($shop, $refreshResult->accessToken);

            ShopifyAccessTokenRefreshed::dispatch($shop, $refreshResult);
        } else {
            Log::debug('Existing AccessToken-model is still valid, no need to refresh it', [
                'shop_id' => $shop->id,
                'access_token_id' => $shop->accessToken->id,
            ]);
        }
    }

    protected function createAndSaveNewAccessToken(Shop $shop): void
    {
        // Exchange ID token for access token
        Log::debug('Exchanging Shopify ID Token for Shopify Access Token');
        $tokenExchangeResult = $this->exchangeIdToken($shop);

        // Store the access token in the Shop model
        Log::debug('Storing newly created Shopify Access Token', [
            'shop_id' => $shop->id,
        ]);
        resolve(SaveTokenExchangeAccessTokenAction::class)->execute($shop, $tokenExchangeResult->accessToken);

        // First offline access token means the app was just (re)installed.
        // This is the extension point for first-install side effects: dispatch
        // jobs here to declare metafield definitions, register additional
        // webhooks, seed defaults, etc. e.g. `MyInstallJob::dispatch($shop);`
    }

    protected function exchangeIdToken(Shop $shop): TokenExchangeResult
    {
        Log::info('Exchanging Shopify ID Token for Shopify Access Token', [
            'shop_id' => $shop->id,
        ]);
        $exchangeResult = $this->shopify->exchangeUsingTokenExchange(
            accessMode: 'offline',
            idToken: $this->verificationResult->idToken,
            invalidTokenResponse: $this->verificationResult->newIdTokenResponse,
        );

        if (! $exchangeResult->ok) {
            Log::error('Failed to exchange Shopify ID Token for Shopify Access Token', [
                'shop_id' => $shop->id,
                'log_code' => $exchangeResult->log?->code,
                'log_detail' => $exchangeResult->log?->detail,
                'response_status' => $exchangeResult->response->status,
                'response_body' => $exchangeResult->response->body,
            ]);

            throw new \RuntimeException('Failed to exchange access token');
        }

        Log::info('Exchanged Shopify ID Token for Shopify Access Token', [
            'shop_id' => $shop->id,
            'log_code' => $exchangeResult->log?->code,
            'log_detail' => $exchangeResult->log?->detail,
        ]);

        return $exchangeResult;
    }

    /**
     * Store verification result data in the request for later use.
     */
    protected function storeResultAsRequestAttributes(ResultWithExchangeableIdToken $result): void
    {
        $this->request->attributes->set('shopify_shop', $result->shop); // eg 'smart-send-develop-eur'
        $this->request->attributes->set('shopify_user_id', $result->userId); // eg 97834828052
        // $this->request->attributes->set('shopify_id_token', $result->idToken); // sensitive information (JWT Session ID Token)
        // $this->request->attributes->set('shopify_verification_result', $result); // sensitive information since it contain the idToken

        Log::debug('Stored Shopify verification result in request attributes', $this->request->attributes->all());
    }

    private function toTokenExchangeAccessTokenArray(string $shopSubdomain, AccessToken $token): array
    {
        Log::info('Exchanging Shopify Access Token for Shopify Access Token', [
            'shop' => $shopSubdomain,
        ]);

        // The shopify-app-php package appends `.myshopify.com` when building the
        // OAuth token endpoint URL, so we must pass the subdomain only.
        return [
            'accessMode' => 'offline',
            'shop' => $shopSubdomain,
            'token' => $token->token,
            'expires' => $token->expires_at?->format('Y-m-d\TH:i:s\Z'),
            'scope' => implode(',', $token->scopes),
            'refreshToken' => $token->refresh_token,
            'refreshTokenExpires' => $token->refresh_token_expires_at?->format('Y-m-d\TH:i:s\Z'),
            'user' => null,
        ];
    }
}
