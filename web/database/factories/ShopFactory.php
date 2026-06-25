<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        return [
            'shop_domain' => fake()->unique()->domainWord().'.myshopify.com',
            'name' => fake()->slug(4),
            'email' => fake()->companyEmail(),
        ];
    }
}
