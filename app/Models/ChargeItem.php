<?php

namespace App\Models;

class ChargeItem extends BaseModel
{
    protected $casts = ['taxable' => 'boolean', 'service_chargeable' => 'boolean', 'is_active' => 'boolean'];
}
