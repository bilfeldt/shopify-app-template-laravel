<?php

use App\Services\Shopify\GraphQL\ThrottleStatus;

describe('ThrottleStatus', function () {
    it('creates from array', function () {
        $data = [
            'maximumAvailable' => 2000,
            'currentlyAvailable' => 1500,
            'restoreRate' => 100.0,
        ];

        $status = ThrottleStatus::fromArray($data);

        expect($status->maximumAvailable)->toBe(2000)
            ->and($status->currentlyAvailable)->toBe(1500)
            ->and($status->restoreRate)->toBe(100.0);
    });

    it('returns null when data is null', function () {
        expect(ThrottleStatus::fromArray(null))->toBeNull();
    });

    it('handles missing values with defaults', function () {
        $status = ThrottleStatus::fromArray([]);

        expect($status->maximumAvailable)->toBe(0)
            ->and($status->currentlyAvailable)->toBe(0)
            ->and($status->restoreRate)->toBe(0.0);
    });

    it('detects low availability', function () {
        $lowStatus = ThrottleStatus::fromArray([
            'maximumAvailable' => 2000,
            'currentlyAvailable' => 50,
            'restoreRate' => 100.0,
        ]);

        $highStatus = ThrottleStatus::fromArray([
            'maximumAvailable' => 2000,
            'currentlyAvailable' => 500,
            'restoreRate' => 100.0,
        ]);

        expect($lowStatus->isLow())->toBeTrue()
            ->and($highStatus->isLow())->toBeFalse();
    });

    it('allows custom threshold for isLow', function () {
        $status = ThrottleStatus::fromArray([
            'maximumAvailable' => 2000,
            'currentlyAvailable' => 200,
            'restoreRate' => 100.0,
        ]);

        expect($status->isLow(100))->toBeFalse()
            ->and($status->isLow(300))->toBeTrue();
    });

    it('calculates wait time in milliseconds', function () {
        $status = ThrottleStatus::fromArray([
            'maximumAvailable' => 2000,
            'currentlyAvailable' => 50,
            'restoreRate' => 50.0,
        ]);

        // Need 50 more points to reach 100, at 50 points/second = 1 second = 1000ms
        expect($status->getWaitMilliseconds(100))->toBe(1000);
    });

    it('returns zero wait time when enough points available', function () {
        $status = ThrottleStatus::fromArray([
            'maximumAvailable' => 2000,
            'currentlyAvailable' => 500,
            'restoreRate' => 100.0,
        ]);

        expect($status->getWaitMilliseconds(100))->toBe(0);
    });

    it('returns zero wait time when restore rate is zero', function () {
        $status = ThrottleStatus::fromArray([
            'maximumAvailable' => 2000,
            'currentlyAvailable' => 50,
            'restoreRate' => 0.0,
        ]);

        expect($status->getWaitMilliseconds(100))->toBe(0);
    });
});
