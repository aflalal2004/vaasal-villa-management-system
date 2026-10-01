<?php

namespace App\Models;

class ChannelSyncLog extends BaseModel
{
    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected $casts = ['payload' => 'array', 'date_from' => 'date', 'date_to' => 'date', 'processed_at' => 'datetime'];
}
