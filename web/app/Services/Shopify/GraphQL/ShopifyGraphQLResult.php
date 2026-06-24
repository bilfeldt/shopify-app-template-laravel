<?php

namespace App\Services\Shopify\GraphQL;

use App\Services\Shopify\Exceptions\GraphQLRequestException;
use Shopify\App\Types\GQLResult;

/**
 * Wraps a successful (ok=true) GQLResult. Error responses are thrown as
 * {@see GraphQLRequestException} from the
 * client, so this object always carries data.
 */
readonly class ShopifyGraphQLResult
{
    public function __construct(
        protected GQLResult $result,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->result->data ?? [];
    }

    public function throttleStatus(): ?ThrottleStatus
    {
        $status = $this->result->extensions['cost']['throttleStatus'] ?? null;

        return ThrottleStatus::fromArray($status);
    }

    public function raw(): GQLResult
    {
        return $this->result;
    }
}
