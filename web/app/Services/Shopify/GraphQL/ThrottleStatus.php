<?php

namespace App\Services\Shopify\GraphQL;

readonly class ThrottleStatus
{
    public function __construct(
        public int $maximumAvailable,
        public int $currentlyAvailable,
        public float $restoreRate,
    ) {}

    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            maximumAvailable: (int) ($data['maximumAvailable'] ?? 0),
            currentlyAvailable: (int) ($data['currentlyAvailable'] ?? 0),
            restoreRate: (float) ($data['restoreRate'] ?? 0),
        );
    }

    public function isLow(int $threshold = 100): bool
    {
        return $this->currentlyAvailable < $threshold;
    }

    public function getWaitMilliseconds(int $targetPoints = 100): int
    {
        if ($this->currentlyAvailable >= $targetPoints || $this->restoreRate <= 0) {
            return 0;
        }

        return (int) (($targetPoints - $this->currentlyAvailable) / $this->restoreRate * 1000);
    }
}
