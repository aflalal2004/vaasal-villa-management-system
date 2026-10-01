<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Kot;
use App\Modules\Core\Exceptions\BusinessRuleException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kitchen Display System: live KOT tickets per station; cooks bump New → Preparing → Ready → Served. */
class KdsController extends Controller
{
    public function index(Request $request)
    {
        return view('pos.kds', ['station' => $request->query('station', 'kitchen')]);
    }

    public function feed(Request $request)
    {
        $station = $request->query('station', 'kitchen');
        $kots = Kot::with(['order.table', 'order.villa', 'order.outlet', 'order.waiter', 'items' => fn ($q) => $q->where('status', '!=', 'void')])
            ->when($station !== 'all', fn ($q) => $q->where('station', $station))
            ->where(fn ($q) => $q->whereIn('status', ['new', 'preparing', 'ready'])->orWhere(fn ($w) => $w->where('status', 'served')->where('served_at', '>=', now()->subMinutes(10))))
            ->orderBy('created_at')->limit(60)->get();

        return response()->json(['now' => now()->toIso8601String(), 'kots' => $kots->map(fn ($k) => [
            'id' => $k->id, 'kot_no' => $k->kot_no, 'station' => $k->station, 'status' => $k->status,
            'where' => $k->order->table ? 'Table '.$k->order->table->name : ($k->order->type === 'room_service' ? 'Villa '.($k->order->villa?->code ?? '') : 'Takeaway'),
            'outlet' => $k->order->outlet->name, 'waiter' => $k->order->waiter?->name, 'created_at' => $k->created_at->toIso8601String(),
            'age' => (int) $k->created_at->diffInMinutes(now()), 'notes' => $k->order->notes,
            'items' => $k->items->map(fn ($i) => ['qty' => (float) $i->quantity, 'name' => $i->name, 'mods' => collect($i->modifiers)->pluck('name')->implode(', '), 'notes' => $i->notes, 'seat' => $i->seat]),
        ])]);
    }

    public function status(Request $request, Kot $kot)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['preparing', 'ready', 'served', 'new'])]]);
        $allowed = ['new' => ['preparing'], 'preparing' => ['ready', 'new'], 'ready' => ['served', 'preparing'], 'served' => ['ready']];
        if (! in_array($data['status'], $allowed[$kot->status] ?? [], true)) {
            throw new BusinessRuleException("Cannot move a {$kot->status} ticket to {$data['status']}.");
        }
        $ts = ['preparing' => 'started_at', 'ready' => 'ready_at', 'served' => 'served_at'][$data['status']] ?? null;
        $kot->update(['status' => $data['status']] + ($ts ? [$ts => now()] : []));
        $itemStatus = ['new' => 'fired', 'preparing' => 'preparing', 'ready' => 'ready', 'served' => 'served'][$data['status']];
        $kot->items()->where('status', '!=', 'void')->update(['status' => $itemStatus]);
        return response()->json(['ok' => true, 'status' => $kot->status]);
    }
}
