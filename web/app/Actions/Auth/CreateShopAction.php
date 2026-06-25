<?php

namespace App\Actions\Auth;

use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class CreateShopAction
{
    public function execute(string $shopDomain): Shop
    {
        Log::info('Creating new Shop-model', ['shop_domain' => $shopDomain]);

        return Shop::create([
            'shop_domain' => $shopDomain,
        ]);
    }
}
