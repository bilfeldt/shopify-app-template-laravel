<?php

namespace App\Actions;

use App\Models\Shop;
use Illuminate\Support\Facades\Log;

/**
 * Deletes the persisted offline access token for a shop.
 *
 * Used when the app is uninstalled (a stale token causes problems if the
 * shop reinstalls) and as part of redacting shop data.
 */
class DeleteAccessTokenAction
{
    public function execute(Shop $shop): void
    {
        if ($shop->accessToken === null) {
            return;
        }

        $shop->accessToken->delete();
        $shop->unsetRelation('accessToken');

        Log::info('Deleted access token for shop', [
            'shop_id' => $shop->id,
            'shop_domain' => $shop->shop_domain,
        ]);
    }
}
