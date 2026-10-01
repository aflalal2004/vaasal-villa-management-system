<?php

use App\Models\Property;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('money')) {
    /** Format an amount in the property currency, e.g. "LKR 12,500.00". */
    function money($amount, ?string $currency = null, bool $symbol = true): string
    {
        $currency ??= config('vaasal.currency', 'LKR');
        $n = number_format((float) $amount, 2);
        return $symbol ? $currency.' '.$n : $n;
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date($date, string $format = 'd M Y'): string
    {
        if (! $date) return '—';
        return ($date instanceof CarbonInterface ? $date : Carbon::parse($date))->format($format);
    }
}

if (! function_exists('fmt_dt')) {
    function fmt_dt($date): string
    {
        return fmt_date($date, 'd M Y, H:i');
    }
}

if (! function_exists('minutes_hm')) {
    function minutes_hm(?int $minutes): string
    {
        if (! $minutes) return '0h 00m';
        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }
}

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        try {
            return Setting::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (! function_exists('property')) {
    function property(): Property
    {
        return Property::current();
    }
}

if (! function_exists('status_tone')) {
    /** Maps any status string to a semantic badge tone used by <x-badge>. */
    function status_tone(?string $status): string
    {
        return match ($status) {
            'confirmed', 'active', 'ready', 'paid', 'completed', 'present', 'approved', 'resolved', 'returned', 'ok', 'available', 'pass', 'processed', 'sent', 'served', 'open_shift' => 'success',
            'checked_in', 'occupied', 'in_progress', 'cleaning', 'preparing', 'fired', 'processing', 'charged_to_room', 'claimed', 'clean' => 'info',
            'hold', 'tentative', 'pending', 'inspection', 'paused', 'late', 'partially_paid', 'issue', 'on_hold', 'billed', 'new', 'medium', 'high', 'incomplete', 'on_leave', 'stored' => 'warning',
            'cancelled', 'no_show', 'expired', 'void', 'failed', 'lost', 'blocked', 'dirty', 'rejected', 'absent', 'out_of_order', 'blocking', 'fail', 'suspended', 'urgent', 'refunded' => 'danger',
            default => 'neutral',
        };
    }
}

if (! function_exists('label')) {
    function label(?string $value): string
    {
        return $value ? ucfirst(str_replace('_', ' ', $value)) : '—';
    }
}

if (! function_exists('whatsapp_link')) {
    function whatsapp_link(?string $text = null, ?string $number = null): string
    {
        $number = preg_replace('/\D/', '', $number ?? contact('whatsapp'));
        return 'https://wa.me/'.$number.($text ? '?text='.rawurlencode($text) : '');
    }
}

if (! function_exists('contact')) {
    /**
     * Business contact details from ONE place: CMS settings, then property record, then config defaults.
     * Keys: phone, phone_link (for tel:), email, whatsapp (digits, international), whatsapp_display (+94…),
     * address, city, country, location ("Jaffna, Sri Lanka").
     */
    function contact(string $key): string
    {
        $cfg = config('vaasal.contact');
        $p = rescue(fn () => property(), null, false);
        $phone = (string) setting('contact_phone', $p?->phone ?: $cfg['phone']);
        $wa = preg_replace('/\D/', '', (string) setting('whatsapp_number', config('vaasal.whatsapp.number')));
        $city = $p?->city ?: $cfg['city'];
        $country = $p?->country ?: $cfg['country'];

        return match ($key) {
            'phone' => $phone,
            'phone_link' => preg_replace('/[^\d+]/', '', $phone),
            'email' => (string) setting('contact_email', $p?->email ?: $cfg['email']),
            'whatsapp' => $wa,
            'whatsapp_display' => '+'.$wa,
            'address' => (string) setting('contact_address', $city.', '.$country),
            'city' => $city,
            'country' => $country,
            'location' => $city.', '.$country,
            default => '',
        };
    }
}

if (! function_exists('price')) {
    /** A base-currency (LKR) amount formatted in the visitor's display currency, e.g. "$41.63". Display only. */
    function price($amount, ?string $currency = null): string
    {
        return app(\App\Modules\Core\Services\CurrencyService::class)->format((float) $amount, $currency);
    }
}

if (! function_exists('asset_v')) {
    /** Public asset URL with a cache-busting version from the file's modification time. */
    function asset_v(string $path): string
    {
        $file = public_path($path);
        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
