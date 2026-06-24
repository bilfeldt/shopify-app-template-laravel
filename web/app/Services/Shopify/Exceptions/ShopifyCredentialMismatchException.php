<?php

namespace App\Services\Shopify\Exceptions;

use RuntimeException;

/**
 * Thrown when Shopify rejects an embedded request in a way that fingerprints a
 * misconfiguration of the app's own credentials: a `SHOPIFY_CLIENT_ID` or
 * `SHOPIFY_CLIENT_SECRET` that does not match the app the store installed.
 *
 * The usual cause is re-linking the app (the `client_id` in `shopify.app.toml`
 * changes) without re-syncing `web/.env`. This is a "cannot happen in a healthy
 * deploy" state, so the guard always throws it — the app is wholly unusable for
 * every merchant until the credentials are fixed. The framework decides what is
 * shown: the full message under `APP_DEBUG`, a generic 500 in production. The
 * exception is still reported either way, so we get alerted.
 *
 * Shopify's verifier reports a distinct code per credential, which we translate
 * into an actionable hint:
 *
 * - `invalid_aud`      → id_token `aud` claim ≠ configured client_id → SHOPIFY_CLIENT_ID
 * - `invalid_id_token` → id_token signature failed to verify         → SHOPIFY_CLIENT_SECRET
 *
 * Only the public client_id is ever included (in the log context, never the
 * merchant-facing response). The client secret is never referenced.
 */
class ShopifyCredentialMismatchException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $reasonCode,
        public readonly string $configuredClientId,
    ) {
        parent::__construct($message);
    }

    public static function fromVerification(string $code, string $detail, string $configuredClientId): self
    {
        $culprit = match ($code) {
            'invalid_aud' => 'SHOPIFY_CLIENT_ID',
            'invalid_id_token' => 'SHOPIFY_CLIENT_SECRET',
            default => 'SHOPIFY_CLIENT_ID and/or SHOPIFY_CLIENT_SECRET',
        };

        return new self(sprintf(
            "Shopify rejected the embedded request with `%s` (%s).\n\n"
            .'This almost always means the env %s does not match the app this store installed. ',
            $code,
            $detail,
            $culprit,
        ), $code, $configuredClientId);
    }

    /**
     * Structured context merged into the log record when this exception is
     * reported, so the reason and (public) client_id are queryable without
     * parsing the message. No secret is ever included.
     *
     * @return array<string, string>
     */
    public function context(): array
    {
        return [
            'shopify_reason_code' => $this->reasonCode,
            'configured_client_id' => $this->configuredClientId,
        ];
    }
}
