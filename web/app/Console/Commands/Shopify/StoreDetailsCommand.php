<?php

namespace App\Console\Commands\Shopify;

use App\Console\Commands\Concerns\HasShopOption;
use Illuminate\Console\Command;

class StoreDetailsCommand extends Command
{
    use HasShopOption;

    protected $signature = 'shopify:store-details {--shop= : The shop domain to fetch details for}';

    protected $description = 'Fetch and display Shopify store details';

    public function handle(): int
    {
        $shop = $this->getShop();
        $data = $shop->shopify()->details();

        if (empty($data)) {
            $this->components->error('Shop not found.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('ID', $data['id']);
        $this->components->twoColumnDetail('Name', $data['name']);
        $this->components->twoColumnDetail('Domain', $data['myshopifyDomain']);
        $this->components->twoColumnDetail('Currency', $data['currencyCode']);
        $this->components->twoColumnDetail('Plan', $data['plan']['publicDisplayName'] ?? 'N/A');
        $this->components->twoColumnDetail('Contact Email', $data['contactEmail']);

        return self::SUCCESS;
    }
}
