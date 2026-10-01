<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\CurrencyService;
use Illuminate\Http\Request;

/** Property profile, taxes and operating times. Integration keys stay in .env (never in the database). */
class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['p' => Property::current(), 'integrations' => [
            'Payment gateway' => config('vaasal.payments.driver').(config('vaasal.payments.driver') === 'stripe' ? (config('vaasal.payments.stripe.secret') ? ' · key set' : ' · STRIPE_SECRET missing') : ' (local sandbox, no card data)'),
            'Channel manager' => config('vaasal.channel.driver') === 'null' ? 'Not connected (CHANNEL_DRIVER=null)' : config('vaasal.channel.driver'),
            'Lock system' => config('vaasal.locks.driver') === 'simulator' ? 'Simulator (development)' : 'Lock Bridge ('.config('vaasal.locks.driver').')',
            'Email' => config('mail.default').(config('mail.default') === 'log' ? ' — messages written to storage/logs' : ''),
            'WhatsApp' => config('vaasal.whatsapp.cloud_token') ? 'Cloud API configured' : 'Click-to-chat links (WHATSAPP_NUMBER)',
            'Weather' => config('vaasal.site.weather_enabled') ? 'Open-Meteo (no key)' : 'Disabled',
            'Exchange rates' => config('vaasal.currency_display.api_key') ? 'API connected (cached '.config('vaasal.currency_display.cache_minutes').' min)' : 'No EXCHANGE_RATE_API_KEY — static / manual rates',
            'Stock media' => collect(['Unsplash' => config('vaasal.media.unsplash_key'), 'Pexels' => config('vaasal.media.pexels_key')])->filter()->keys()->implode(', ') ?: 'No keys — own gallery only',
            'RFID simulator' => config('vaasal.locks.rfid_simulator') ? 'Enabled (RFID_SIMULATOR=true)' : 'Disabled',
        ], 'fx' => app(CurrencyService::class)->snapshot(), 'currencies' => app(CurrencyService::class)->currencies(),
           'override' => json_decode((string) \App\Models\Setting::get('currency_rates'), true) ?: []]);
    }

    public function update(Request $request)
    {
        $p = Property::current();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'legal_name' => ['nullable', 'string', 'max:150'], 'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'], 'tax_id' => ['nullable', 'string', 'max:60'],
            'tax_pct' => ['required', 'numeric', 'min:0', 'max:50'], 'service_charge_pct' => ['required', 'numeric', 'min:0', 'max:30'],
            'check_in_time' => ['required', 'date_format:H:i'], 'check_out_time' => ['required', 'date_format:H:i'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $p->fill($data);
        AuditService::logChanges('settings', $p, 'property_updated');
        $p->save();
        return back()->with('success', 'Settings saved. New rates and charges use the updated tax and service percentages.');
    }
    /** Settings → Currencies: manual display rates (per 1 LKR). Blank fields fall back to the API or static rates. */
    public function currencies(Request $request, CurrencyService $fx)
    {
        $data = $request->validate(['rates' => ['nullable', 'array'], 'rates.*' => ['nullable', 'numeric', 'gt:0', 'lt:1000']]);
        $fx->saveOverride(array_filter($data['rates'] ?? [], fn ($v) => $v !== null && $v !== ''));
        AuditService::log('settings', 'currency_rates_updated', null, json_encode($data['rates'] ?? []));
        return back()->with('success', 'Display currency rates saved. Prices are still stored and charged in LKR.');
    }

    public function refreshRates(CurrencyService $fx)
    {
        $ok = $fx->refreshApi();
        return back()->with($ok ? 'success' : 'warning', $ok ? 'Live exchange rates refreshed.' : 'The exchange-rate API is not configured or unavailable; static/manual rates are in use.');
    }
}