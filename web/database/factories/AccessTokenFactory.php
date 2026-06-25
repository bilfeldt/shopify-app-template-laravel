<?php

namespace Database\Factories;

use App\Enums\AccessTokenMode;
use App\Models\AccessToken;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessToken>
 */
class AccessTokenFactory extends Factory
{
    protected $model = AccessToken::class;

    /**
     * Valid Shopify Access Scopes for generating realistic test data.
     *
     * @var array<string>
     *
     * @see https://shopify.dev/docs/api/usage/access-scopes
     */
    private const SHOPIFY_SCOPES = [
        // **Authenticated access scopes**
        'read_all_orders',
        'write_app_proxy',
        'read_assigned_fulfillment_orders',
        'write_assigned_fulfillment_orders',
        'read_merchant_managed_fulfillment_orders',
        'write_merchant_managed_fulfillment_orders',
        'read_third_party_fulfillment_orders',
        'write_third_party_fulfillment_orders',
        'read_marketplace_fulfillment_orders',
        'read_cart_transforms',
        'write_cart_transforms',
        'read_checkout_branding_settings',
        'write_checkout_branding_settings',
        'read_content',
        'write_content',
        'read_online_store_pages',
        'read_customer_events',
        'write_pixels',
        'read_customer_merge',
        'write_customer_merge',
        'read_customer_payment_methods',
        'read_customers',
        'write_customers',
        'read_delivery_customizations',
        'write_delivery_customizations',
        'read_discounts',
        'write_discounts',
        'read_draft_orders',
        'write_draft_orders',
        'read_files',
        'write_files',
        'read_fulfillments',
        'write_fulfillments',
        'read_gift_cards',
        'write_gift_cards',
        'read_inventory',
        'write_inventory',
        'read_legal_policies',
        'read_locales',
        'write_locales',
        'read_locations',
        'write_locations',
        'read_markets',
        'write_markets',
        'read_marketing_events',
        'write_marketing_events',
        'read_merchant_approval_signals',
        'read_metaobject_definitions',
        'write_metaobject_definitions',
        'read_metaobjects',
        'write_metaobjects',
        'read_online_store_navigation',
        'write_online_store_navigation',
        'read_order_edits',
        'write_order_edits',
        'read_orders',
        'write_orders',
        'read_own_subscription_contracts',
        'write_own_subscription_contracts',
        'read_payment_customizations',
        'write_payment_customizations',
        'read_payment_gateways',
        'write_payment_gateways',
        'read_payment_mandate',
        'write_payment_mandate',
        'write_payment_sessions',
        'read_payment_terms',
        'write_payment_terms',
        'read_price_rules',
        'write_price_rules',
        'write_privacy_settings',
        'read_privacy_settings',
        'read_products',
        'write_products',
        'read_purchase_options',
        'write_purchase_options',
        'read_reports',
        'read_returns',
        'write_returns',
        'read_script_tags',
        'write_script_tags',
        'read_shipping',
        'write_shipping',
        'read_shopify_payments_disputes',
        'read_shopify_payments_dispute_evidences',
        'read_shopify_payments_payouts',
        'read_store_credit_accounts',
        'read_store_credit_account_transactions',
        'read_store_credit_account_transactions',
        'read_themes',
        'write_themes',
        'read_translations',
        'read_users',
        'read_validations',
        'write_validations',
        // *Unauthenticated access scopes*
        // Provide apps with read-only access to the Storefront API. Unauthenticated access
        // is intended for interacting with a store on behalf of a customer.
        // 'unauthenticated_read_checkouts'
        // 'unauthenticated_write_checkouts'
        // 'unauthenticated_read_customers'
        // 'unauthenticated_write_customers'
        // 'unauthenticated_read_customer_tags'
        // 'unauthenticated_read_content'
        // 'unauthenticated_read_metaobjects'
        // 'unauthenticated_read_product_inventory'
        // 'unauthenticated_read_product_listings'
        // 'unauthenticated_read_product_pickup_locations'
        // 'unauthenticated_read_product_tags'
        // 'unauthenticated_read_selling_plans'
        // *Customer access scopes*
        // Provide apps with read and write access to the Customer Account API. Customer access
        // is intended for interacting with data that belongs to a customer.
        // 'customer_read_customers'
        // 'customer_write_customers'
        // 'customer_read_orders'
        // 'customer_write_orders'
        // 'customer_read_draft_orders'
        // 'customer_read_markets'
        // 'customer_read_metaobjects'
        // 'customer_read_store_credit_accounts'
        // 'customer_read_own_subscription_contracts'
        // 'customer_write_own_subscription_contracts'
        // 'customer_write_subscription_contracts'
        // 'customer_read_companies'
        // 'customer_write_companies'
        // 'customer_read_locations'
        // 'customer_write_locations'
    ];

    /**
     * Default: non-expiring offline access token.
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'token' => 'shpat_'.fake()->bothify(str_repeat('*', 32)), // Prefixed with shpat_ (for public apps), shpca_ (for custom apps), or shppa_ (for legacy private apps)
            'mode' => AccessTokenMode::Offline,
            'scopes' => fake()->randomElements(self::SHOPIFY_SCOPES, fake()->numberBetween(3, 6)),
        ];
    }

    public function offlineNonExpiring(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
            'refresh_token' => null,
            'refresh_token_expires_at' => null,
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('+1 hour', '+90 days'),
            'refresh_token' => 'shprt_'.fake()->bothify(str_repeat('*', 32)),
            'refresh_token_expires_at' => fake()->dateTimeBetween('+1 day', '+1 year'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTime('now'),
        ]);
    }

    public function expiredRefreshToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'refresh_token_expires_at' => fake()->dateTime('now'),
        ]);
    }
}
