@extends('layouts.admin')
@section('title', 'Rates & seasons')
@section('content')
<x-page-header title="Rates & seasons" sub="Nightly price = seasonal rate for the villa type (or its base rate outside seasons), adjusted by the rate plan. Changes push to the channel manager.">
    <button class="btn" type="button" data-modal-open="#season-modal"><x-icon name="plus" /> Season</button>
    <button class="btn" type="button" data-modal-open="#plan-modal"><x-icon name="plus" /> Rate plan</button>
</x-page-header>

<div class="card mb">
    <div class="card-head"><h2>Seasonal rate matrix</h2><span class="small muted">Nightly rate · minimum stay</span></div>
    <form method="post" action="{{ route('admin.rates.matrix') }}">
        @csrf
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Villa type</th><th class="num">Base</th>
                @foreach ($seasons as $s)<th><span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:{{ $s->color }}"></span> {{ $s->name }}<div class="small muted" style="text-transform:none;letter-spacing:0">{{ fmt_date($s->start_date, 'd M y') }} – {{ fmt_date($s->end_date, 'd M y') }}</div></th>@endforeach
            </tr></thead>
            <tbody>
            @foreach ($types as $t)
                <tr>
                    <td><strong>{{ $t->name }}</strong></td>
                    <td class="num">{{ money($t->base_rate, null, false) }}</td>
                    @foreach ($seasons as $s)
                        @php $r = $rates[$t->id.'-'.$s->id] ?? null; @endphp
                        <td style="min-width:150px">
                            <div class="row" style="flex-wrap:nowrap;gap:4px">
                                <input type="number" step="0.01" min="0" name="rates[{{ $t->id }}][{{ $s->id }}][amount]" value="{{ $r?->amount }}" placeholder="base" aria-label="{{ $t->name }} {{ $s->name }} rate" style="min-width:90px">
                                <input type="number" min="1" max="30" name="rates[{{ $t->id }}][{{ $s->id }}][min_stay]" value="{{ $r?->min_stay ?? 1 }}" aria-label="Minimum stay" style="width:56px" title="Minimum nights">
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="card-foot"><span class="small muted">Leave a rate blank to use the base rate for that season.</span><span class="spacer"></span><button class="btn btn-primary" type="submit">Save rates</button></div>
    </form>
</div>

<div class="grid cols-2">
    <div class="card">
        <div class="card-head"><h2>Seasons</h2></div>
        <div class="table-wrap"><table class="table">
            <tbody>
            @foreach ($seasons as $s)
                <tr><td><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:{{ $s->color }}"></span> {{ $s->name }}</td>
                    <td class="small nowrap">{{ fmt_date($s->start_date) }} – {{ fmt_date($s->end_date) }}</td><td class="small">priority {{ $s->priority }}</td>
                    <td class="actions"><form method="post" action="{{ route('admin.rates.seasons.destroy', $s) }}" data-confirm="Delete season {{ $s->name }}?" data-danger>@csrf @method('delete')<button class="btn btn-sm btn-ghost" type="submit" aria-label="Delete"><x-icon name="trash" /></button></form></td></tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Rate plans</h2></div>
        <div class="table-wrap"><table class="table">
            <tbody>
            @foreach ($plans as $p)
                <tr>
                    <td><strong>{{ $p->name }}</strong> <span class="mono small muted">{{ $p->code }}</span> @if(! $p->is_active)<x-badge status="inactive" />@endif
                        <div class="small muted">{{ $p->mealPlanLabel() }} · {{ $p->price_adjust_pct > 0 ? '+' : '' }}{{ (float) $p->price_adjust_pct }}% · deposit {{ (float) $p->deposit_pct }}% ·
                            {{ $p->is_refundable ? 'free cancel '.$p->free_cancel_days.'d, then '.(float) $p->cancel_penalty_pct.'%' : 'non-refundable' }}</div></td>
                    <td class="actions"><button class="btn btn-sm btn-ghost" type="button" data-modal-open="#plan-{{ $p->id }}" aria-label="Edit"><x-icon name="edit" /></button></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
</div>

<x-modal id="season-modal" title="New season">
    <form method="post" action="{{ route('admin.rates.seasons.store') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-input name="name" label="Name" required col="f-8" />
            <x-input name="color" type="color" label="Colour" value="#0E6B63" col="f-4" />
            <x-input name="start_date" type="date" label="Start" required />
            <x-input name="end_date" type="date" label="End (inclusive)" required />
            <x-input name="priority" type="number" min="1" max="9" label="Priority" value="2" required col="f-6" help="Higher wins where seasons overlap" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Add season</button></div>
    </form>
</x-modal>

@foreach ($plans->concat([new \App\Models\RatePlan(['is_active' => true, 'is_public' => true, 'is_refundable' => true, 'free_cancel_days' => 7, 'cancel_penalty_pct' => 100, 'deposit_pct' => 30, 'price_adjust_pct' => 0, 'meal_plan' => 'room_only'])]) as $p)
<x-modal :id="$p->exists ? 'plan-'.$p->id : 'plan-modal'" :title="$p->exists ? 'Edit '.$p->name : 'New rate plan'">
    <form method="post" action="{{ $p->exists ? route('admin.rates.plans.update', $p) : route('admin.rates.plans.store') }}">
        @csrf @if($p->exists) @method('put') @endif
        <div class="modal-body form-grid">
            <x-input name="code" label="Code" :value="$p->code" required col="f-4" :id="'pc'.$p->id" />
            <x-input name="name" label="Name" :value="$p->name" required col="f-8" :id="'pn'.$p->id" />
            <x-select name="meal_plan" label="Meal plan" :options="\App\Models\RatePlan::MEAL_PLANS" :value="$p->meal_plan" col="f-6" :id="'pm'.$p->id" />
            <x-input name="price_adjust_pct" type="number" step="0.01" label="Price adjustment %" :value="$p->price_adjust_pct" required col="f-6" :id="'pa'.$p->id" />
            <x-input name="deposit_pct" type="number" step="0.01" min="0" max="100" label="Deposit %" :value="$p->deposit_pct" required col="f-4" :id="'pd'.$p->id" />
            <x-input name="free_cancel_days" type="number" min="0" label="Free cancel (days)" :value="$p->free_cancel_days" required col="f-4" :id="'pf'.$p->id" />
            <x-input name="cancel_penalty_pct" type="number" step="0.01" min="0" max="100" label="Penalty %" :value="$p->cancel_penalty_pct" required col="f-4" :id="'pp'.$p->id" />
            <x-textarea name="description" label="Policy text shown to guests" :value="$p->description" rows="2" :id="'pt'.$p->id" />
            <x-checkbox name="is_refundable" label="Refundable" :checked="$p->is_refundable" col="f-4" />
            <x-checkbox name="is_public" label="Sold on website" :checked="$p->is_public" col="f-4" />
            <x-checkbox name="is_active" label="Active" :checked="$p->is_active" col="f-4" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
</x-modal>
@endforeach
@endsection
