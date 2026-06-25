<?php

namespace App\Workflows;

use App\Actions\DeleteAccessTokenAction;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

/**
 * Deletes a shop and all data associated with it.
 *
 * Used to redact shop data (shop/redact compliance webhook), but reusable
 * anywhere a shop must be removed entirely.
 *
 * This is a workflow (not an action) because it composes other actions:
 * workflows may call actions, actions never call actions or workflows, and
 * workflows never call other workflows.
 */
class DeleteShopWorkflow
{
    public function __construct(protected DeleteAccessTokenAction $deleteAccessToken) {}

    public function execute(Shop $shop): void
    {
        $shopDomain = $shop->shop_domain;

        $this->deleteAccessToken->execute($shop);
        $shop->delete();

        Log::info('Deleted shop and all associated data', [
            'shop_domain' => $shopDomain,
        ]);
    }
}
