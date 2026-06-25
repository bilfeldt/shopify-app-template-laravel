<?php

namespace App\Services\Shopify\Webhooks\Handlers;

use App\Services\Shopify\Webhooks\ShopifyWebhook;
use App\Services\Shopify\Webhooks\WebhookHandler;
use Illuminate\Support\Facades\Log;

/**
 * Handles the customers/data_request GDPR compliance webhook.
 *
 * The app does not persist any customer data (orders are read on demand from
 * the Shopify API and never stored), so there is no data to compile. The
 * request is logged for the audit trail.
 *
 * @see https://shopify.dev/docs/apps/build/compliance/privacy-law-compliance
 */
class CustomersDataRequestHandler implements WebhookHandler
{
    public function handle(ShopifyWebhook $webhook): void
    {
        Log::info('Received customers/data_request compliance webhook - no customer data is stored by this app', [
            'shop_domain' => $webhook->shopDomain,
            'webhook_id' => $webhook->webhookId,
            'customer_id' => $webhook->payload['customer']['id'] ?? null,
            'data_request_id' => $webhook->payload['data_request']['id'] ?? null,
        ]);
    }
}
