<?php

namespace App\Services\Shopify\GraphQL;

readonly class GraphQLError
{
    /**
     * @param  array<array{line: int, column: int}>|null  $locations
     * @param  array<string|int>|null  $path
     * @param  array<string, mixed>|null  $extensions
     */
    public function __construct(
        public string $message,
        public ?array $locations = null,
        public ?array $path = null,
        public ?array $extensions = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message'] ?? '',
            locations: $data['locations'] ?? null,
            path: $data['path'] ?? null,
            extensions: $data['extensions'] ?? null,
        );
    }

    public function getCode(): ?string
    {
        return $this->extensions['code'] ?? null;
    }
}
