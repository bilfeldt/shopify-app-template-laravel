<?php

namespace App\Services\Shopify\Exceptions;

use RuntimeException;

class AccessTokenStillValidException extends RuntimeException
{
    public function __construct(string $message = 'Access token is still valid, no refresh was performed')
    {
        parent::__construct($message);
    }
}
