<?php

use App\Models\AccessToken;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('contract')
    ->in('Contract');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * CI fallback credentials for the Contract tier: the TEST_STORE_DOMAIN +
 * TEST_STORE_OFFLINE_TOKEN env vars, set as secrets where there is no local dev
 * database to read from. Locally the dev database is used instead — see
 * {@see contractShop()}.
 *
 * Returns null when unset so callers can skip cleanly.
 *
 * @return array{domain: string, token: string}|null
 */
function testStoreCredentials(): ?array
{
    $domain = env('TEST_STORE_DOMAIN');
    $token = env('TEST_STORE_OFFLINE_TOKEN');

    if (empty($domain) || empty($token)) {
        return null;
    }

    return ['domain' => $domain, 'token' => $token];
}

/**
 * The installed shop in the local dev database (the one `shopify app dev`
 * installs and keeps refreshed). Returns null when there is no dev database
 * (e.g. CI) or no installed shop.
 *
 * The connection to the dev sqlite file is defined here on demand — test-only,
 * read-only — so it never enters the production `config/database.php`. Pinned to
 * the real file via its own env var so the testing `:memory:` DB_DATABASE
 * override can't point it elsewhere.
 */
function devDatabaseShop(): ?Shop
{
    config([
        'database.connections.shopify_dev' => [
            'driver' => 'sqlite',
            'database' => env('SHOPIFY_DEV_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],
    ]);

    try {
        return Shop::on('shopify_dev')->whereHas('accessToken')->latest('id')->first();
    } catch (Throwable) {
        return null;
    }
}

/**
 * Whether a dev-database token is safe to copy and use without triggering a
 * refresh during the test. A refresh would rotate Shopify's single-use refresh
 * token and invalidate the dev install; we'd rather skip and let `npm run dev`
 * keep the token fresh. The margin sits comfortably above the proactive-refresh
 * buffer so a quick test never crosses it mid-run.
 */
function devTokenUsable(Shop $shop): bool
{
    $token = $shop->accessToken;

    return $token !== null
        && ($token->expires_at === null || $token->expires_at->isAfter(now()->addMinutes(5)));
}

/**
 * Resolve a Shop for the Contract tier, bound to a real, usable offline token.
 *
 * Locally it copies the live shop + offline token out of the dev database into
 * the isolated (rolled-back) test database, so the contract shop is an ordinary
 * fixture on the default connection — visible to in-process artisan commands.
 * Only a comfortably valid token is copied (see {@see devTokenUsable()}), so the
 * test never refreshes; when the dev token is near expiry the resolver returns
 * null and the test skips. In CI (no dev database) it falls back to the
 * TEST_STORE_* env secrets. Returns null when neither is available.
 */
function contractShop(): ?Shop
{
    $dev = devDatabaseShop();

    if ($dev !== null && devTokenUsable($dev)) {
        $shop = Shop::factory()->create(['shop_domain' => $dev->shop_domain]);
        AccessToken::factory()->for($shop)->create([
            'token' => $dev->accessToken->token,
            'mode' => $dev->accessToken->mode,
            'scopes' => $dev->accessToken->scopes,
            'expires_at' => $dev->accessToken->expires_at,
            'refresh_token' => $dev->accessToken->refresh_token,
            'refresh_token_expires_at' => $dev->accessToken->refresh_token_expires_at,
        ]);
        $shop->refresh();

        return $shop;
    }

    $credentials = testStoreCredentials();

    if ($credentials === null) {
        return null;
    }

    $shop = Shop::factory()->create(['shop_domain' => $credentials['domain']]);
    AccessToken::factory()->for($shop)->offlineNonExpiring()->create(['token' => $credentials['token']]);
    $shop->refresh();

    return $shop;
}

/**
 * Whether a Contract-tier shop can be resolved — a usable local dev install or
 * the CI env secrets — without creating anything. Used by Contract skip guards.
 */
function contractShopAvailable(): bool
{
    $dev = devDatabaseShop();

    return ($dev !== null && devTokenUsable($dev)) || testStoreCredentials() !== null;
}
