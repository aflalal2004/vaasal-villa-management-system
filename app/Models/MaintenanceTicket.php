<?php

namespace App\Models;

class MaintenanceTicket extends BaseModel
{
    protected $attributes = ['status' => 'open', 'severity' => 'medium', 'category' => 'general', 'blocks_inventory' => false];

    protected $casts = ['blocks_inventory' => 'boolean', 'started_at' => 'datetime', 'resolved_at' => 'datetime'];

    public const SEVERITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'blocking' => 'Blocking (out of order)'];
    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'on_hold' => 'On hold', 'resolved' => 'Resolved', 'closed' => 'Closed'];
    public const CATEGORIES = ['general' => 'General', 'plumbing' => 'Plumbing', 'electrical' => 'Electrical', 'ac' => 'Air conditioning',
        'furniture' => 'Furniture', 'pool' => 'Pool', 'appliance' => 'Appliance', 'lock' => 'Door lock'];

    public function villa() { return $this->belongsTo(Villa::class); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by'); }
    public function assignee() { return $this->belongsTo(Employee::class, 'assigned_to'); }
    public function block() { return $this->belongsTo(VillaBlock::class, 'villa_block_id'); }
}
