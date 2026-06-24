<?php

namespace App\Services\Shopify\Exceptions;

use App\Models\Shop;
use Exception;
use Shopify\App\Types\TokenExchangeResult;
use Throwable;

/**
 * Base class for any failure originating from the shopify-app-php package's
 * token-exchange / refresh flow. Concrete subclasses correspond to the
 * `log_code` values returned by the package.
 *
 * Subclasses opt into one of two shared response strategies via the
 * intermediate abstracts {@see ShopifyReauthRequiredException} and
 * {@see ShopifyAuthTransientException}, or fall through to the default
 * 500 handling on this base for unexpected codes.
 */
abstract class ShopifyAuthException extends Exception
{
    public function __construct(
        public readonly TokenExchangeResult $result,
        public readonly ?Shop $shop = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf(
                '[%s] %s',
                $result->log?->code ?? 'unknown',
                $result->log?->detail ?? 'Shopify authentication failed.',
            ),
            previous: $previous,
        );
    }

    /**
     * Translate a not-ok TokenExchangeResult into the matching exception type.
     *
     * Centralised so call sites (the auth guard and ShopifyShop) stay DRY and
     * any new `log_code` introduced by the package surfaces consistently as
     * a {@see ShopifyUnexpectedAuthException}.
     */
    public static function fromResult(TokenExchangeResult $result, ?Shop $shop = null): self
    {
        return match ($result->log?->code) {
            'refresh_token_expired' => new ShopifyRefreshTokenExpiredException($result, $shop),
            'invalid_grant' => new ShopifyInvalidGrantException($result, $shop),
            'invalid_client' => new ShopifyInvalidClientException($result, $shop),
            'network_error' => new ShopifyNetworkErrorException($result, $shop),
            'server_error' => new ShopifyServerErrorException($result, $shop),
            default => new ShopifyUnexpectedAuthException($result, $shop),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'shop_id' => $this->shop?->id,
            'shop_domain' => $this->shop?->shop_domain,
            'log_code' => $this->result->log?->code,
            'log_detail' => $this->result->log?->detail,
            'response_status' => $this->result->response?->status,
        ];
    }
}
