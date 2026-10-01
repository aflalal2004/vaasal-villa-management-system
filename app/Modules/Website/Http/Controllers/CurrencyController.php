<?php

namespace App\Modules\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Display-currency switcher for the public website. Prices stay in LKR in the database. */
class CurrencyController extends Controller
{
    public function __construct(private CurrencyService $fx) {}

    /** POST /currency — remember the visitor's display currency for a year. */
    public function switch(Request $request)
    {
        $data = $request->validate(['currency' => ['required', 'string', Rule::in(array_keys($this->fx->currencies()))]]);
        $cookie = cookie(config('vaasal.currency_display.cookie'), $data['currency'], 60 * 24 * 365, null, null, null, false, false, 'lax');
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'currency' => $data['currency']] + $this->payload())->withCookie($cookie);
        }
        return back()->withCookie($cookie);
    }

    /** GET /api/v1/currencies — rates per 1 LKR, for the currency selector. */
    public function rates()
    {
        return response()->json($this->payload())->header('Cache-Control', 'public, max-age=900');
    }

    private function payload(): array
    {
        $snap = $this->fx->snapshot();
        return [
            'base' => $this->fx->base(),
            'currencies' => $this->fx->currencies(),
            'rates' => $snap['rates'],
            'source' => $snap['source'],
            'updated_at' => $snap['updated_at'],
        ];
    }
}
