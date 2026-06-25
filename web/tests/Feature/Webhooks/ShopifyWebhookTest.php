<?php

use App\Models\AccessToken;
use App\Models\Shop;
use App\Services\Shopify\Webhooks\Handlers\AppUninstalledHandler;
use App\Services\Shopify\Webhooks\ShopifyWebhook;
use App\Services\Shopify\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Shopify\App\ShopifyApp;

pest()->group('webhooks', 'controllers');

beforeEach(function () {
    config([
        'shopify.client_id' => 'test-client-id',
        'shopify.client_secret' => 'test-client-secret',
    ]);

    // The ShopifyApp singleton captures config at construction time, so make
    // sure it is rebuilt with the test credentials above.
    app()->forgetInstance(ShopifyApp::class);
});

/**
 * Send a webhook request to the canonical endpoint, signed like Shopify would.
 *
 * Each call gets a fresh webhook id unless one is given explicitly. Pass an
 * empty string to omit the header (like `shopify app webhook trigger` does).
 *
 * @param  array<string, mixed>  $payload
 */
function postSignedWebhook(
    string $topic,
    array $payload = [],
    string $shopDomain = 'test-shop.myshopify.com',
    ?string $hmac = null,
    ?string $webhookId = null,
    ?string $eventId = null,
): TestResponse {
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $hmac ??= base64_encode(hash_hmac('sha256', $body, config('shopify.client_secret'), true));
    $webhookId ??= (string) Str::uuid();
    $eventId ??= (string) Str::uuid();

    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHOPIFY_TOPIC' => $topic,
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shopDomain,
        'HTTP_X_SHOPIFY_API_VERSION' => '2026-04',
    ];

    if ($webhookId !== '') {
        $server['HTTP_X_SHOPIFY_WEBHOOK_ID'] = $webhookId;
    }

    if ($eventId !== '') {
        $server['HTTP_X_SHOPIFY_EVENT_ID'] = $eventId;
    }

    return test()->call('POST', '/webhooks/shopify', server: $server, content: $body);
}

describe('HMAC verification', function () {
    it('rejects a webhook with an invalid HMAC signature', function () {
        $response = postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], hmac: base64_encode('not-the-right-signature'));

        $response->assertUnauthorized();
    });

    it('rejects a webhook signed with the wrong secret', function () {
        $body = json_encode(['domain' => 'test-shop.myshopify.com']);
        $wrongHmac = base64_encode(hash_hmac('sha256', $body, 'wrong-secret', true));

        $response = postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], hmac: $wrongHmac);

        $response->assertUnauthorized();
    });

    it('rejects a webhook with a missing HMAC header', function () {
        $body = json_encode(['domain' => 'test-shop.myshopify.com']);

        $response = $this->call('POST', '/webhooks/shopify', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_TOPIC' => 'app/uninstalled',
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'test-shop.myshopify.com',
        ], content: $body);

        $response->assertBadRequest();
    });

    it('accepts a webhook with a valid HMAC signature', function () {
        $response = postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com']);

        $response->assertOk();
    });

    it('does not touch any shop when the HMAC is invalid', function () {
        $shop = Shop::factory()->create(['shop_domain' => 'test-shop.myshopify.com']);
        AccessToken::factory()->for($shop)->offlineNonExpiring()->create();

        postSignedWebhook('app/uninstalled', ['domain' => $shop->shop_domain], shopDomain: $shop->shop_domain, hmac: base64_encode('invalid'));

        expect($shop->refresh()->uninstalled_at)->toBeNull()
            ->and($shop->accessToken)->not->toBeNull();
    });
});

describe('topic dispatch', function () {
    it('dispatches the webhook to the handler registered for the topic', function () {
        $spy = $this->spy(AppUninstalledHandler::class);

        postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], webhookId: 'webhook-id-1', eventId: 'event-id-1')->assertOk();

        $spy->shouldHaveReceived('handle')
            ->once()
            ->withArgs(fn (ShopifyWebhook $webhook) => $webhook->topic === 'app/uninstalled'
                && $webhook->shopDomain === 'test-shop.myshopify.com'
                && $webhook->webhookId === 'webhook-id-1'
                && $webhook->eventId === 'event-id-1'
                && $webhook->apiVersion === '2026-04'
                && $webhook->payload === ['domain' => 'test-shop.myshopify.com']);
    });

    it('acknowledges a topic without a registered handler with a 200', function () {
        postSignedWebhook('products/create', ['id' => 123])->assertOk();
    });

    it('has a handler registered for every topic declared in shopify.app.toml', function (string $topic) {
        $handlers = (fn () => $this->handlers)->call(app(WebhookDispatcher::class));

        expect($handlers)->toHaveKey($topic);
    })->with([
        'app/uninstalled',
        'customers/data_request',
        'customers/redact',
        'shop/redact',
    ]);
});

describe('idempotency', function () {
    it('keeps holding the webhook id lock after successful processing', function () {
        postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], webhookId: 'webhook-id-1', eventId: 'event-id-1')->assertOk();

        // The held lock IS the dedup record: it must not be released on
        // success, so it cannot be re-acquired.
        expect(Cache::lock('shopify-webhook:webhook-id-1', 10)->get())->toBeFalse();
    });

    it('skips a duplicate delivery, responds with success and logs the duplicate', function () {
        $spy = $this->spy(AppUninstalledHandler::class);
        Log::spy();

        postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], webhookId: 'webhook-id-1', eventId: 'event-id-1')->assertOk();
        postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], webhookId: 'webhook-id-1', eventId: 'event-id-1')->assertOk();

        $spy->shouldHaveReceived('handle')->once();

        Log::shouldHaveReceived('info')
            ->with('Skipping duplicate Shopify webhook delivery - webhook id has already been processed', [
                'webhook_id' => 'webhook-id-1',
                'event_id' => 'event-id-1',
                'topic' => 'app/uninstalled',
                'shop_domain' => 'test-shop.myshopify.com',
            ])
            ->once();
    });

    it('treats a webhook id whose lock is held by a concurrent delivery as a duplicate', function () {
        // Simulates losing the race against a concurrent identical delivery:
        // the claim is an atomic cache lock on the webhook id, so a lock that
        // is already held means another request already won.
        Cache::lock('shopify-webhook:webhook-id-1', 60)->get();

        $spy = $this->spy(AppUninstalledHandler::class);

        postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], webhookId: 'webhook-id-1')->assertOk();

        $spy->shouldNotHaveReceived('handle');
    });

    it('processes deliveries with different webhook ids even when they share an event id', function () {
        $spy = $this->spy(AppUninstalledHandler::class);

        postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], webhookId: 'webhook-id-1', eventId: 'shared-event-id')->assertOk();
        postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], webhookId: 'webhook-id-2', eventId: 'shared-event-id')->assertOk();

        $spy->shouldHaveReceived('handle')->twice();
    });

    it('processes a delivery without a webhook id header and logs a warning', function () {
        $spy = $this->spy(AppUninstalledHandler::class);
        Log::spy();

        postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], webhookId: '', eventId: '')->assertOk();

        $spy->shouldHaveReceived('handle')->once();

        Log::shouldHaveReceived('warning')
            ->with('Shopify webhook delivered without X-Shopify-Webhook-Id header - skipping deduplication', Mockery::type('array'))
            ->once();
    });

    it('releases the lock when the handler throws so the retry is processed', function () {
        $this->mock(AppUninstalledHandler::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Handler failed'));

        postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], webhookId: 'webhook-id-1')->assertServerError();

        // Shopify retries with the same webhook id - the retry must be processed.
        $spy = $this->spy(AppUninstalledHandler::class);

        postSignedWebhook('app/uninstalled', ['domain' => 'test-shop.myshopify.com'], webhookId: 'webhook-id-1')->assertOk();

        $spy->shouldHaveReceived('handle')->once();
    });
});

describe('app/uninstalled', function () {
    it('deletes the access token and marks the shop as uninstalled', function () {
        $shop = Shop::factory()->create(['shop_domain' => 'test-shop.myshopify.com']);
        AccessToken::factory()->for($shop)->offlineNonExpiring()->create();

        postSignedWebhook('app/uninstalled', ['domain' => $shop->shop_domain], shopDomain: $shop->shop_domain)->assertOk();

        $shop->refresh();

        expect($shop->uninstalled_at)->not->toBeNull()
            ->and($shop->accessToken)->toBeNull();

        $this->assertDatabaseCount('access_tokens', 0);
    });

    it('responds 200 even when the shop is unknown', function () {
        postSignedWebhook('app/uninstalled', ['domain' => 'unknown.myshopify.com'], shopDomain: 'unknown.myshopify.com')->assertOk();
    });
});

describe('compliance topics', function () {
    it('acknowledges a customers/data_request webhook', function () {
        $shop = Shop::factory()->create(['shop_domain' => 'test-shop.myshopify.com']);

        postSignedWebhook('customers/data_request', [
            'shop_id' => 1,
            'shop_domain' => $shop->shop_domain,
            'customer' => ['id' => 42, 'email' => 'customer@example.com'],
            'orders_requested' => [299938, 280263],
            'data_request' => ['id' => 9999],
        ], shopDomain: $shop->shop_domain)->assertOk();
    });

    it('acknowledges a customers/redact webhook', function () {
        $shop = Shop::factory()->create(['shop_domain' => 'test-shop.myshopify.com']);

        postSignedWebhook('customers/redact', [
            'shop_id' => 1,
            'shop_domain' => $shop->shop_domain,
            'customer' => ['id' => 42, 'email' => 'customer@example.com'],
            'orders_to_redact' => [299938, 280263],
        ], shopDomain: $shop->shop_domain)->assertOk();
    });

    it('deletes the shop and access token on shop/redact', function () {
        $shop = Shop::factory()->create(['shop_domain' => 'test-shop.myshopify.com']);
        AccessToken::factory()->for($shop)->offlineNonExpiring()->create();

        postSignedWebhook('shop/redact', [
            'shop_id' => 1,
            'shop_domain' => $shop->shop_domain,
        ], shopDomain: $shop->shop_domain)->assertOk();

        $this->assertDatabaseMissing('shops', ['shop_domain' => $shop->shop_domain]);
        $this->assertDatabaseCount('access_tokens', 0);
    });

    it('responds 200 to shop/redact for an unknown shop', function () {
        postSignedWebhook('shop/redact', [
            'shop_id' => 1,
            'shop_domain' => 'unknown.myshopify.com',
        ], shopDomain: 'unknown.myshopify.com')->assertOk();
    });
});

describe('routing', function () {
    it('registers the canonical webhook route', function () {
        expect(route('webhooks.shopify'))->toEndWith('/webhooks/shopify');
    });

    it('rejects non-POST requests', function () {
        $this->get('/webhooks/shopify')->assertStatus(405);
    });
});
