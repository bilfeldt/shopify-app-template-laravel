<?php

namespace App\Services\Shopify\Exceptions;

use App\Services\Shopify\GraphQL\GraphQLError;
use RuntimeException;
use Shopify\App\Types\GQLResult;

class GraphQLRequestException extends RuntimeException implements ShopifyGraphQLExceptionInterface
{
    /** @var array<GraphQLError> */
    private array $graphQLErrors;

    /**
     * @param  array<GraphQLError>  $graphQLErrors
     */
    public function __construct(
        string $message,
        protected string $shopDomain,
        protected GQLResult $result,
        array $graphQLErrors = [],
    ) {
        parent::__construct(message: $message);
        $this->graphQLErrors = $graphQLErrors;
    }

    public static function fromResult(string $shopDomain, GQLResult $result): self
    {
        $errors = self::parseErrorsFromBody($result);
        $message = $result->log?->detail ?? 'Shopify GraphQL request failed';

        if (! empty($errors)) {
            $message = $errors[0]->message;
        }

        return new self(
            message: $message,
            shopDomain: $shopDomain,
            result: $result,
            graphQLErrors: $errors,
        );
    }

    public function getShopDomain(): string
    {
        return $this->shopDomain;
    }

    public function getRawResult(): GQLResult
    {
        return $this->result;
    }

    /**
     * @return array<GraphQLError>
     */
    public function getErrors(): array
    {
        return $this->graphQLErrors;
    }

    public function getLogCode(): ?string
    {
        return $this->result->log?->code;
    }

    /**
     * @return array<GraphQLError>
     */
    private static function parseErrorsFromBody(GQLResult $result): array
    {
        if ($result->log?->code !== 'graphql_errors') {
            return [];
        }

        $body = json_decode($result->response->body, true);
        $errors = $body['errors'] ?? [];

        return array_map(
            fn (array $error) => GraphQLError::fromArray($error),
            $errors,
        );
    }
}
