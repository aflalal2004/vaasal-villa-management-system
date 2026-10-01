<?php

namespace App\Modules\Website\Services;

use App\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Current weather + 5-day forecast from Open-Meteo (free, no API key), cached for 20 minutes. */
class WeatherService
{
    private const CODES = [
        0 => ['Clear sky', 'sun'], 1 => ['Mainly clear', 'sun-cloud'], 2 => ['Partly cloudy', 'sun-cloud'], 3 => ['Overcast', 'cloud'], 45 => ['Fog', 'fog'], 48 => ['Fog', 'fog'],
        51 => ['Light drizzle', 'rain'], 53 => ['Drizzle', 'rain'], 55 => ['Drizzle', 'rain'], 61 => ['Light rain', 'rain'], 63 => ['Rain', 'rain'], 65 => ['Heavy rain', 'rain'],
        80 => ['Rain showers', 'rain'], 81 => ['Rain showers', 'rain'], 82 => ['Heavy showers', 'storm'], 95 => ['Thunderstorm', 'storm'], 96 => ['Thunderstorm', 'storm'], 99 => ['Thunderstorm', 'storm'],
    ];

    public function forecast(): array
    {
        if (! config('vaasal.site.weather_enabled')) {
            return ['ok' => false];
        }
        $p = Property::current();
        $lat = $p->latitude ?? config('vaasal.site.latitude');
        $lon = $p->longitude ?? config('vaasal.site.longitude');

        return Cache::remember("vv.weather.v2.$lat.$lon", now()->addMinutes(20), function () use ($lat, $lon) {
            try {
                $r = Http::timeout(6)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $lat, 'longitude' => $lon, 'current' => 'temperature_2m,weather_code,wind_speed_10m',
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min', 'forecast_days' => 5, 'timezone' => config('app.timezone'),
                ]);
                if (! $r->successful()) {
                    return ['ok' => false];
                }
                $c = $r->json('current');
                $d = $r->json('daily');
                return [
                    'ok' => true,
                    'current' => ['temp' => $c['temperature_2m'], 'wind' => $c['wind_speed_10m'], 'label' => self::CODES[$c['weather_code']][0] ?? 'Tropical', 'icon' => self::CODES[$c['weather_code']][1] ?? 'sun-cloud'],
                    'daily' => collect($d['time'])->map(fn ($t, $i) => ['day' => Carbon::parse($t)->format('D'), 'max' => $d['temperature_2m_max'][$i], 'min' => $d['temperature_2m_min'][$i],
                        'icon' => self::CODES[$d['weather_code'][$i]][1] ?? 'sun-cloud'])->all(),
                ];
            } catch (\Throwable $e) {
                Log::info('Weather unavailable', ['error' => $e->getMessage()]);
                return ['ok' => false];
            }
        });
    }
}
