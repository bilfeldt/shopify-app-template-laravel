<?php

namespace App\Services\Shopify\Webhooks;

use Illuminate\Http\Request;

/**
 * Immutable value object representing an incoming Shopify webhook request.
 *
 * Built from the standard Shopify webhook headers and the JSON payload.
 *
 * @see https://shopify.dev/docs/apps/build/webhooks
 */
readonly class ShopifyWebhook
{
    /**
     * @param  string  $webhookId  Unique per delivery - used as the idempotency/deduplication key.
     * @param  string  $eventId  Correlation id - the same merchant action can produce multiple deliveries with different webhook ids but the same event id.
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $topic,
        public string $shopDomain,
        public string $webhookId,
        public string $eventId,
        public string $apiVersion,
        public array $payload,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            topic: (string) $request->header('X-Shopify-Topic'),
            shopDomain: (string) $request->header('X-Shopify-Shop-Domain'),
            webhookId: (string) $request->header('X-Shopify-Webhook-Id'),
            eventId: (string) $request->header('X-Shopify-Event-Id'),
            apiVersion: (string) $request->header('X-Shopify-API-Version'),
            payload: $request->json()->all(),
        );
    }
}
