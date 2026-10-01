<?php

namespace App\Modules\Villa\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Rate;
use App\Models\RatePlan;
use App\Models\Season;
use App\Models\VillaType;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Seasons, seasonal rate matrix (villa type × season), and rate plans. */
class RateController extends Controller
{
    public function index()
    {
        $seasons = Season::where('end_date', '>=', now()->subMonths(2))->orderBy('start_date')->get();
        return view('admin.villas.rates', [
            'types' => VillaType::where('is_active', true)->orderBy('sort_order')->get(),
            'seasons' => $seasons,
            'rates' => Rate::whereIn('season_id', $seasons->pluck('id'))->get()->keyBy(fn ($r) => $r->villa_type_id.'-'.$r->season_id),
            'plans' => RatePlan::orderBy('id')->get(),
        ]);
    }

    public function saveMatrix(Request $request)
    {
        $data = $request->validate(['rates' => ['array'], 'rates.*.*.amount' => ['nullable', 'numeric', 'min:0'], 'rates.*.*.min_stay' => ['nullable', 'integer', 'min:1', 'max:30']]);
        $n = 0;
        foreach ($data['rates'] ?? [] as $typeId => $bySeason) {
            foreach ($bySeason as $seasonId => $r) {
                if (($r['amount'] ?? '') === '' || $r['amount'] === null) {
                    Rate::where('villa_type_id', $typeId)->where('season_id', $seasonId)->delete();
                    continue;
                }
                Rate::updateOrCreate(['villa_type_id' => $typeId, 'season_id' => $seasonId], ['amount' => $r['amount'], 'min_stay' => $r['min_stay'] ?: 1]);
                $n++;
            }
        }
        AuditService::log('rates', 'matrix_saved', null, $n.' seasonal rates saved');
        app(\App\Modules\Channel\Services\ChannelSyncService::class)->queueAri(now(), now()->addDays(90));
        return back()->with('success', 'Seasonal rates saved.');
    }

    public function storeSeason(Request $request)
    {
        $s = Season::create($this->season($request) + ['property_id' => Property::current()->id]);
        AuditService::log('rates', 'season_created', $s, $s->name);
        return back()->with('success', 'Season "'.$s->name.'" added. Enter its rates in the matrix.');
    }

    public function updateSeason(Request $request, Season $season)
    {
        $season->fill($this->season($request));
        AuditService::logChanges('rates', $season);
        $season->save();
        return back()->with('success', 'Season updated.');
    }

    public function destroySeason(Season $season)
    {
        AuditService::log('rates', 'season_deleted', $season, $season->name);
        $season->delete();
        return back()->with('success', 'Season deleted; base rates apply to those dates.');
    }

    public function storePlan(Request $request)
    {
        $p = RatePlan::create($this->plan($request) + ['property_id' => Property::current()->id]);
        AuditService::log('rates', 'plan_created', $p, $p->name);
        return back()->with('success', 'Rate plan created.');
    }

    public function updatePlan(Request $request, RatePlan $plan)
    {
        $plan->fill($this->plan($request, $plan));
        AuditService::logChanges('rates', $plan);
        $plan->save();
        return back()->with('success', 'Rate plan saved.');
    }

    private function season(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'priority' => ['required', 'integer', 'min:1', 'max:9'],
        ]) + ['color' => $request->input('color', '#0E6B63')];
    }

    private function plan(Request $request, ?RatePlan $plan = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('rate_plans', 'code')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'meal_plan' => ['required', Rule::in(array_keys(RatePlan::MEAL_PLANS))],
            'free_cancel_days' => ['required', 'integer', 'min:0', 'max:365'],
            'cancel_penalty_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'deposit_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'price_adjust_pct' => ['required', 'numeric', 'min:-90', 'max:200'],
        ]);
        return $data + ['is_refundable' => $request->boolean('is_refundable'), 'is_public' => $request->boolean('is_public'), 'is_active' => $request->boolean('is_active')];
    }
}
