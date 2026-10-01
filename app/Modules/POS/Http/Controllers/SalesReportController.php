<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Restaurant POS → Restaurant sales. Same ReportService as the back office, limited to restaurant reports,
 * so restaurant managers never need access to the hotel back office.
 */
class SalesReportController extends Controller
{
    public const TYPES = ['pos_sales', 'pos_items', 'inventory'];

    public function __invoke(Request $request, ReportService $reports, ?string $type = null)
    {
        // No type given: open the first restaurant report this user may see (e.g. stock valuation for stores/kitchen).
        $type ??= collect(self::TYPES)->first(fn ($t) => $request->user()->hasPermission(ReportService::TYPES[$t][1])) ?? 'pos_sales';
        abort_unless(in_array($type, self::TYPES, true), 404);
        abort_unless($request->user()->hasPermission(ReportService::TYPES[$type][1]), 403);
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->query('to', now()->toDateString()));
        if ($to->lt($from)) [$from, $to] = [$to, $from];
        if ($from->diffInDays($to) > 400) $from = $to->copy()->subDays(400);

        $r = $reports->run($type, $from, $to);
        if ($request->query('export') === 'csv') {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, array_values($r['columns']));
            foreach ($r['rows'] as $row) {
                fputcsv($out, array_map(fn ($k) => is_float($row[$k] ?? null) ? round($row[$k], 2) : ($row[$k] ?? ''), array_keys($r['columns'])));
            }
            rewind($out);
            return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$type.'-'.$from->toDateString().'-'.$to->toDateString().'.csv"']);
        }
        return view('pos.sales', ['r' => $r, 'all' => collect(ReportService::TYPES)->only(self::TYPES)->filter(fn ($x) => $request->user()->hasPermission($x[1]))]);
    }
}
