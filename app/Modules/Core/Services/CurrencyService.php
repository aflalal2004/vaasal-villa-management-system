<?php

namespace App\Modules\Core\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Display-currency conversion. The ONE place exchange rates live.
 *
 * - Money is stored, charged, invoiced and paid in the base currency (LKR). Conversions are for display only.
 * - Rate priority: admin override (Settings → Currencies) > exchange-rate API (cached) > config fallback rates.
 * - Arithmetic uses bcmath strings so large LKR amounts do not pick up float drift before the final rounding.
 */
class CurrencyService
{
    public const CACHE_KEY = 'vv.fx.api';

    private ?array $memo = null;

    public function base(): string
    {
        return config('vaasal.currency_display.base', 'LKR');
    }

    /** @return array<string, array{name:string,symbol:string,decimals:int}> */
    public function currencies(): array
    {
        return config('vaasal.currency_display.currencies');
    }

    public function isSupported(?string $code): bool
    {
        return $code !== null && array_key_exists(strtoupper($code), $this->currencies());
    }

    /** Currency chosen by the visitor (cookie), falling back to the base currency. */
    public function current(): string
    {
        $code = strtoupper((string) request()?->cookie(config('vaasal.currency_display.cookie')));
        return $this->isSupported($code) ? $code : $this->base();
    }

    /** @return array{rates: array<string,float>, source: string, updated_at: ?string} */
    public function snapshot(): array
    {
        return $this->memo ??= $this->buildSnapshot();
    }

    private function buildSnapshot(): array
    {
        $fallback = config('vaasal.currency_display.fallback_rates');
        $api = $this->apiRates();
        $override = $this->overrideRates();

        $rates = [];
        foreach (array_keys($this->currencies()) as $code) {
            $rates[$code] = $code === $this->base() ? 1.0 : (float) ($override[$code] ?? $api['rates'][$code] ?? $fallback[$code] ?? 0);
        }
        $source = $override ? 'manual' : ($api['rates'] ? 'api' : 'static');
        return ['rates' => $rates, 'source' => $source, 'updated_at' => $override ? Setting::get('currency_rates_updated_at') : $api['updated_at']];
    }

    public function rate(string $code): float
    {
        return $this->snapshot()['rates'][strtoupper($code)] ?? 0.0;
    }

    /** Convert a base-currency amount, rounded to the target currency's decimals. */
    public function convert(float|int|string $amount, ?string $to = null): float
    {
        $to = strtoupper($to ?? $this->current());
        $decimals = $this->currencies()[$to]['decimals'] ?? 2;
        if ($to === $this->base()) {
            return round((float) $amount, $decimals);
        }
        $rate = $this->rate($to);
        if ($rate <= 0) {
            return round((float) $amount, $decimals);
        }
        $product = function_exists('bcmul')
            ? bcmul(number_format((float) $amount, 2, '.', ''), sprintf('%.10F', $rate), $decimals + 2)
            : (string) ((float) $amount * $rate);
        return round((float) $product, $decimals);
    }

    /** "Rs 12,500" / "$41.63". Falls back to the base currency if the rate is unknown. */
    public function format(float|int|string $amount, ?string $to = null): string
    {
        $to = strtoupper($to ?? $this->current());
        if ($to !== $this->base() && $this->rate($to) <= 0) {
            $to = $this->base();
        }
        $meta = $this->currencies()[$to];
        $value = number_format($this->convert($amount, $to), $meta['decimals']);
        return in_array($meta['symbol'], ['Rs', 'AED'], true) ? $meta['symbol'].' '.$value : $meta['symbol'].$value;
    }

    /** Settings → Currencies: manual rates per 1 LKR. Empty array clears the override. */
    public function saveOverride(array $rates): void
    {
        $clean = [];
        foreach ($rates as $code => $rate) {
            $code = strtoupper((string) $code);
            if ($code !== $this->base() && $this->isSupported($code) && is_numeric($rate) && (float) $rate > 0) {
                $clean[$code] = (float) $rate;
            }
        }
        Setting::put('currency_rates', $clean ? json_encode($clean) : null);
        Setting::put('currency_rates_updated_at', $clean ? now()->toIso8601String() : null);
        $this->memo = null;
    }

    public function refreshApi(): bool
    {
        Cache::forget(self::CACHE_KEY);
        $this->memo = null;
        return (bool) $this->apiRates()['rates'];
    }

    private function overrideRates(): array
    {
        $raw = Setting::get('currency_rates');
        $rates = $raw ? json_decode($raw, true) : [];
        return is_array($rates) ? array_filter($rates, fn ($r) => is_numeric($r) && $r > 0) : [];
    }

    /** Rates from the API, cached. A failed call is cached briefly so a slow API never slows every page. */
    private function apiRates(): array
    {
        $key = config('vaasal.currency_display.api_key');
        if (! $key) {
            return ['rates' => [], 'updated_at' => null];
        }
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $res = Http::timeout(6)->acceptJson()->get(rtrim(config('vaasal.currency_display.api_url'), '/').'/'.$key.'/latest/'.$this->base());
            $rates = array_intersect_key($res->successful() ? (array) $res->json('conversion_rates') : [], $this->currencies());
            if (! $rates) {
                throw new \RuntimeException('HTTP '.$res->status().' '.$res->json('error-type'));
            }
            $result = ['rates' => array_map('floatval', $rates), 'updated_at' => now()->toIso8601String()];
            Cache::put(self::CACHE_KEY, $result, now()->addMinutes(config('vaasal.currency_display.cache_minutes')));
            return $result;
        } catch (\Throwable $e) {
            Log::warning('Exchange-rate API unavailable; using fallback rates.', ['error' => $e->getMessage()]);
            $result = ['rates' => [], 'updated_at' => null];
            Cache::put(self::CACHE_KEY, $result, now()->addMinutes(10));
            return $result;
        }
    }
}
