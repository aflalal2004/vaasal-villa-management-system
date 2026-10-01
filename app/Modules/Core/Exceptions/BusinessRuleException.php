<?php

namespace App\Modules\Core\Exceptions;

use RuntimeException;

/**
 * Thrown by domain services when a business rule blocks an action
 * (e.g. villa not ready, folio has a balance, card encoder offline).
 * Rendered as a friendly error flash / JSON 422, never a 500 page.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'BUSINESS_RULE')
    {
        parent::__construct($message);
    }
}
