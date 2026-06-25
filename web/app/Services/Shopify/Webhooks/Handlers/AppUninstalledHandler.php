<?php

namespace App\Services\Shopify\Webhooks\Handlers;

use App\Actions\DeleteAccessTokenAction;
use App\Actions\MarkShopAsUninstalledAction;
use App\Models\Shop;
use App\Services\Shopify\Webhooks\ShopifyWebhook;
use App\Services\Shopify\Webhooks\WebhookHandler;
use Illuminate\Support\Facades\Log;

/**
 * Handles the app/uninstalled webhook.
 *
 * Deletes the persisted offline access token and marks the shop as
 * uninstalled. The Shop row itself is kept until the shop/redact webhook
 * arrives ~48 hours later.
 */
class AppUninstalledHandler implements WebhookHandler
{
    public function __construct(
        protected DeleteAccessTokenAction $deleteAccessToken,
        protected MarkShopAsUninstalledAction $markShopAsUninstalled,
    ) {}

    public function handle(ShopifyWebhook $webhook): void
    {
        $shop = Shop::query()->where('shop_domain', $webhook->shopDomain)->first();

        if ($shop === null) {
            Log::warning('Received app/uninstalled webhook for unknown shop', [
                'shop_domain' => $webhook->shopDomain,
                'webhook_id' => $webhook->webhookId,
            ]);

            return;
        }

        $this->deleteAccessToken->execute($shop);
        $this->markShopAsUninstalled->execute($shop);
    }
}
