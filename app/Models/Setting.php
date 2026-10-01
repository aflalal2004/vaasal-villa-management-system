<?php

namespace App\Models;

class Setting extends BaseModel
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    public static function get(string $key, $default = null)
    {
        $all = \Illuminate\Support\Facades\Cache::rememberForever('vv.settings', fn () => static::query()->pluck('value', 'key')->all());
        return $all[$key] ?? $default;
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        \Illuminate\Support\Facades\Cache::forget('vv.settings');
    }
}
