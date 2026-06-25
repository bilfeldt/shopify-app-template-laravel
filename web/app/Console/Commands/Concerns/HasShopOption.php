<?php

namespace App\Console\Commands\Concerns;

use App\Models\Shop;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use function Laravel\Prompts\search;

trait HasShopOption
{
    protected function getShop(): Shop
    {
        $shopDomain = $this->option('shop');

        if ($shopDomain) {
            $shop = Shop::where('shop_domain', $shopDomain)->first();

            if (! $shop) {
                throw new ModelNotFoundException("Shop not found: {$shopDomain}");
            }

            return $shop;
        }

        $shopId = search(
            label: 'Search for a shop',
            options: fn (string $value) => strlen($value) > 0
                ? Shop::search($value)->pluck('shop_domain', 'id')->all()
                : [],
        );

        return Shop::findOrFail($shopId);
    }
}
