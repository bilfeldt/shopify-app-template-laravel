<?php

use App\Services\Shopify\GraphQL\GraphQLError;

describe('GraphQLError', function () {
    it('creates from array', function () {
        $data = [
            'message' => 'Field not found',
            'locations' => [['line' => 1, 'column' => 5]],
            'path' => ['shop', 'name'],
            'extensions' => ['code' => 'ACCESS_DENIED'],
        ];

        $error = GraphQLError::fromArray($data);

        expect($error->message)->toBe('Field not found')
            ->and($error->locations)->toBe([['line' => 1, 'column' => 5]])
            ->and($error->path)->toBe(['shop', 'name'])
            ->and($error->extensions)->toBe(['code' => 'ACCESS_DENIED']);
    });

    it('handles missing values', function () {
        $error = GraphQLError::fromArray([]);

        expect($error->message)->toBe('')
            ->and($error->locations)->toBeNull()
            ->and($error->path)->toBeNull()
            ->and($error->extensions)->toBeNull();
    });

    it('extracts code from extensions', function () {
        $error = GraphQLError::fromArray([
            'message' => 'Error',
            'extensions' => ['code' => 'THROTTLED'],
        ]);

        expect($error->getCode())->toBe('THROTTLED');
    });

    it('returns null code when extensions is null', function () {
        $error = GraphQLError::fromArray([
            'message' => 'Error',
        ]);

        expect($error->getCode())->toBeNull();
    });

    it('returns null code when code is missing from extensions', function () {
        $error = GraphQLError::fromArray([
            'message' => 'Error',
            'extensions' => ['other' => 'value'],
        ]);

        expect($error->getCode())->toBeNull();
    });
});
