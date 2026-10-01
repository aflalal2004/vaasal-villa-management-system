<?php

namespace App\Models;

class LinenMovement extends BaseModel
{
    public function item() { return $this->belongsTo(LinenItem::class, 'linen_item_id'); }
    public function task() { return $this->belongsTo(HkTask::class, 'hk_task_id'); }
}
