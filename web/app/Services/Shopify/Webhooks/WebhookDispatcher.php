<?php

namespace App\Services\Shopify\Webhooks;

use App\Services\Shopify\Webhooks\Handlers\AppUninstalledHandler;
use App\Services\Shopify\Webhooks\Handlers\CustomersDataRequestHandler;
use App\Services\Shopify\Webhooks\Handlers\CustomersRedactHandler;
use App\Services\Shopify\Webhooks\Handlers\ShopRedactHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Routes verified Shopify webhooks to their topic handler.
 *
 * All webhook topics are delivered to the single canonical endpoint
 * /webhooks/shopify (see shopify.app.toml) and dispatched here based on the
 * X-Shopify-Topic header. To support a new topic, implement
 * {@see WebhookHandler} and add the topic to the $handlers map below.
 *
 * Duplicate deliveries (Shopify retries on timeouts) are detected centrally
 * here via {@see WebhookDeduplicator}, so individual handlers never see the
 * same webhook id twice and do not need their own idempotency logic.
 */
class WebhookDispatcher
{
    /**
     * Map of Shopify webhook topic to handler class.
     *
     * @var array<string, class-string<WebhookHandler>>
     */
    protected array $handlers = [
        'app/uninstalled' => AppUninstalledHandler::class,
        'customers/data_request' => CustomersDataRequestHandler::class,
        'customers/redact' => CustomersRedactHandler::class,
        'shop/redact' => ShopRedactHandler::class,
    ];

    public function __construct(protected WebhookDeduplicator $deduplicator) {}

    public function dispatch(ShopifyWebhook $webhook): void
    {
        $handlerClass = $this->handlers[$webhook->topic] ?? null;

        if ($handlerClass === null) {
            // Acknowledge with 200 anyway (handled by the controller) so
            // Shopify does not retry a topic we have no handler for.
            Log::warning('Received Shopify webhook with no registered handler', [
                'topic' => $webhook->topic,
                'shop_domain' => $webhook->shopDomain,
                'webhook_id' => $webhook->webhookId,
                'event_id' => $webhook->eventId,
            ]);

            return;
        }

        if (! $this->deduplicator->claim($webhook)) {
            Log::info('Skipping duplicate Shopify webhook delivery - webhook id has already been processed', [
                'webhook_id' => $webhook->webhookId,
                'event_id' => $webhook->eventId,
                'topic' => $webhook->topic,
                'shop_domain' => $webhook->shopDomain,
            ]);

            return;
        }

        try {
            app($handlerClass)->handle($webhook);
        } catch (Throwable $exception) {
            // Release the idempotency claim so Shopify's retry of this
            // webhook id is processed instead of being skipped.
            $this->deduplicator->release($webhook);

            throw $exception;
        }
    }
}
