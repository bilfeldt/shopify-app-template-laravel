<?php

namespace App\Http\Controllers\Shopify\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Shopify\Webhooks\ShopifyWebhook;
use App\Services\Shopify\Webhooks\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Single canonical endpoint for all Shopify webhook topics.
 *
 * HMAC verification is handled by the VerifyShopifyWebhook middleware before
 * this controller runs. Topic routing happens in WebhookDispatcher - register
 * new topic handlers there.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, WebhookDispatcher $dispatcher): Response
    {
        $webhook = ShopifyWebhook::fromRequest($request);

        Log::info('Handling Shopify webhook', [
            'topic' => $webhook->topic,
            'shop_domain' => $webhook->shopDomain,
            'webhook_id' => $webhook->webhookId,
            'api_version' => $webhook->apiVersion,
        ]);

        $dispatcher->dispatch($webhook);

        return response()->noContent(Response::HTTP_OK);
    }
}
