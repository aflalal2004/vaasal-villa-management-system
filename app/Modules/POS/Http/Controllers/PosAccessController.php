<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * "POS System" entry point: Website/Admin → POS access → sign in if needed → POS terminal.
 * Authorised users go straight in; signed-in users without POS rights get a clear explanation, never the terminal.
 */
class PosAccessController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            $request->session()->put('url.intended', route('pos.terminal'));
            return view('pos.access', ['state' => 'guest']);
        }
        if (! $user->isStaff() || ! $user->hasPermission('pos.access')) {
            return response()->view('pos.access', ['state' => 'denied', 'user' => $user], 403);
        }
        return redirect()->route($user->hasPermission('pos.order|pos.bill') ? 'pos.terminal'
            : ($user->hasPermission('pos.kds') ? 'pos.kds' : ($user->hasPermission('inventory.view') ? 'pos.inventory.items.index' : 'pos.today')));
    }
}
