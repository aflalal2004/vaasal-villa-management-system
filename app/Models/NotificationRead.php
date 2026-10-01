<?php

namespace App\Models;

class NotificationRead extends BaseModel
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'app_notification_id';
    protected $casts = ['read_at' => 'datetime'];
}
