<?php

namespace App\Models;

class RatePlan extends BaseModel
{
    protected $casts = ['is_refundable' => 'boolean', 'is_public' => 'boolean', 'is_active' => 'boolean'];

    public const MEAL_PLANS = ['room_only' => 'Room only', 'bb' => 'Bed & breakfast', 'hb' => 'Half board', 'fb' => 'Full board', 'ai' => 'All inclusive'];

    public function mealPlanLabel(): string { return self::MEAL_PLANS[$this->meal_plan] ?? $this->meal_plan; }
}
