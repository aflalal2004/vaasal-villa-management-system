<?php

namespace App\Modules\Booking\Exceptions;

use App\Modules\Core\Exceptions\BusinessRuleException;

/**
 * Raised when the inventory ledger's UNIQUE(villa_id, stay_date) rejects a write,
 * i.e. the villa is already taken for at least one requested night.
 */
class InventoryConflictException extends BusinessRuleException
{
    public function __construct(string $message = 'The selected villa is no longer available for these dates.')
    {
        parent::__construct($message, 'INVENTORY_CONFLICT');
    }
}
