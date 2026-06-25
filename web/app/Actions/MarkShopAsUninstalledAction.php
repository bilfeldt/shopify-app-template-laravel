<?php

namespace App\Actions;

use App\Models\Shop;
use App\Workflows\DeleteShopWorkflow;
use Illuminate\Support\Facades\Log;

/**
 * Marks a shop as uninstalled by stamping uninstalled_at.
 *
 * The Shop row itself is kept until the shop/redact compliance webhook
 * arrives (~48 hours after uninstall), see {@see DeleteShopWorkflow}.
 */
class MarkShopAsUninstalledAction
{
    public function execute(Shop $shop): void
    {
        $shop->forceFill(['uninstalled_at' => now()])->save();

        Log::info('Marked shop as uninstalled', [
            'shop_id' => $shop->id,
            'shop_domain' => $shop->shop_domain,
        ]);
    }
}
