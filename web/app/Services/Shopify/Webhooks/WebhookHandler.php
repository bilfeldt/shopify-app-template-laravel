<?php

namespace App\Services\Shopify\Webhooks;

/**
 * Contract for Shopify webhook topic handlers.
 *
 * Handlers run synchronously within the webhook request, which must complete
 * within Shopify's 5-second timeout. Keep handlers fast and dispatch a queued
 * job for any heavy lifting.
 *
 * Register implementations in {@see WebhookDispatcher::$handlers}.
 */
interface WebhookHandler
{
    public function handle(ShopifyWebhook $webhook): void;
}
