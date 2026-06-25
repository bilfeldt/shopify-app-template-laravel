<?php

namespace App\Services\Shopify\Webhooks;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Deduplicates Shopify webhook deliveries using the X-Shopify-Webhook-Id.
 *
 * Shopify may deliver the same webhook more than once (timeouts, retries).
 * The claim is an atomic cache lock keyed on the webhook id with a 24-hour
 * duration: the first delivery acquires the lock and keeps it - the held
 * lock IS the deduplication record. It is never released on success and
 * simply expires after 24 hours. Concurrent or repeated deliveries fail to
 * acquire the lock and are skipped. Requires a cache store with atomic lock
 * support (database, redis, file and array all qualify).
 *
 * DB-backed storage of processed webhooks was considered and deliberately
 * deferred - this class is the single place to swap if that is ever needed.
 *
 * @see https://shopify.dev/docs/apps/build/webhooks/verify-deliveries#ignoring-duplicates
 */
class WebhookDeduplicator
{
    /**
     * How long a claimed webhook id blocks duplicate deliveries.
     */
    protected const LOCK_SECONDS = 60 * 60 * 24;

    /**
     * Locks acquired by this instance, kept so release() can free the exact
     * lock owner when a handler fails.
     *
     * @var array<string, Lock>
     */
    protected array $acquiredLocks = [];

    /**
     * Atomically claim a webhook delivery for processing.
     *
     * Returns true when this delivery is new (lock acquired now) and should
     * be processed, false when the webhook id has been seen before (lock
     * already held). Deliveries without a webhook id (e.g. `shopify app
     * webhook trigger` from the CLI) cannot be deduplicated and are always
     * claimed.
     */
    public function claim(ShopifyWebhook $webhook): bool
    {
        if ($webhook->webhookId === '') {
            Log::warning('Shopify webhook delivered without X-Shopify-Webhook-Id header - skipping deduplication', [
                'topic' => $webhook->topic,
                'shop_domain' => $webhook->shopDomain,
                'event_id' => $webhook->eventId,
            ]);

            return true;
        }

        $lock = Cache::lock($this->lockKey($webhook), self::LOCK_SECONDS);

        if (! $lock->get()) {
            return false;
        }

        // Deliberately NOT released on success: the held lock is the dedup
        // record until it expires. Kept here so release() can free it if the
        // handler throws.
        $this->acquiredLocks[$webhook->webhookId] = $lock;

        return true;
    }

    /**
     * Release a previously claimed webhook delivery.
     *
     * Called when the handler throws so that Shopify's retry of the same
     * webhook id is processed instead of being skipped as a duplicate.
     */
    public function release(ShopifyWebhook $webhook): void
    {
        $lock = $this->acquiredLocks[$webhook->webhookId] ?? null;

        if ($lock === null) {
            return;
        }

        $lock->release();
        unset($this->acquiredLocks[$webhook->webhookId]);
    }

    protected function lockKey(ShopifyWebhook $webhook): string
    {
        return 'shopify-webhook:'.$webhook->webhookId;
    }
}
