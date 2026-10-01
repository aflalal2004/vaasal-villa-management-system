<?php

namespace App\Models;

class HkTaskItem extends BaseModel
{
    protected $attributes = ['is_checked' => false];

    public $timestamps = false;
    protected $casts = ['is_checked' => 'boolean', 'checked_at' => 'datetime'];

    public function task() { return $this->belongsTo(HkTask::class, 'hk_task_id'); }
}
