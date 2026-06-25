<?php

namespace App\Services\Shopify\Webhooks\Handlers;

use App\Models\Shop;
use App\Services\Shopify\Webhooks\ShopifyWebhook;
use App\Services\Shopify\Webhooks\WebhookHandler;
use App\Workflows\DeleteShopWorkflow;
use Illuminate\Support\Facades\Log;

/**
 * Handles the shop/redact GDPR compliance webhook.
 *
 * Sent ~48 hours after a shop uninstalls the app. Deletes the Shop row and
 * any remaining access token so no shop data is retained.
 *
 * @see https://shopify.dev/docs/apps/build/compliance/privacy-law-compliance
 */
class ShopRedactHandler implements WebhookHandler
{
    public function __construct(protected DeleteShopWorkflow $deleteShop) {}

    public function handle(ShopifyWebhook $webhook): void
    {
        $shop = Shop::query()->where('shop_domain', $webhook->shopDomain)->first();

        if ($shop === null) {
            Log::info('Received shop/redact compliance webhook for unknown shop - nothing to redact', [
                'shop_domain' => $webhook->shopDomain,
                'webhook_id' => $webhook->webhookId,
            ]);

            return;
        }

        $this->deleteShop->execute($shop);
    }
}
