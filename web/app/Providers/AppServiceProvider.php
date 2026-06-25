<?php

namespace App\Providers;

use App\Auth\Guards\ShopifyAppHomeGuard;
use App\Services\Shopify\ShopifyShopFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Shopify\App\ShopifyApp;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShopifyApp::class, fn () => new ShopifyApp(
            clientId: config('shopify.client_id'),
            clientSecret: config('shopify.client_secret'),
        ));

        $this->app->singleton(ShopifyShopFactory::class, fn (Application $app) => new ShopifyShopFactory(
            shopifyApp: $app->make(ShopifyApp::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerShopifyAuthGuards();
    }

    /**
     * Register the Shopify authentication guard.
     *
     * This guard handles both session tokens (from initial page loads)
     * and bearer tokens (from API requests) via verifyAppHomeReq().
     */
    protected function registerShopifyAuthGuards(): void
    {
        Auth::extend(ShopifyAppHomeGuard::DRIVER_NAME, function (Application $app, $name, array $config) {
            return new ShopifyAppHomeGuard(
                Auth::createUserProvider($config['provider']),
                $app->make(ShopifyApp::class),
                $app['request']
            );
        });
    }
}
