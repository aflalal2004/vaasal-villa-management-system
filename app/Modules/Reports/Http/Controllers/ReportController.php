<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $available = collect(ReportService::TYPES)->filter(fn ($r) => $user->hasPermission($r[1]));
        return view('admin.reports.index', ['reports' => $available]);
    }

    public function show(Request $request, string $type, ReportService $reports)
    {
        abort_unless(isset(ReportService::TYPES[$type]), 404);
        abort_unless($request->user()->hasPermission(ReportService::TYPES[$type][1]), 403);

        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->query('to', now()->toDateString()));
        if ($to->lt($from)) [$from, $to] = [$to, $from];
        if ($from->diffInDays($to) > 400) $from = $to->copy()->subDays(400);

        $report = $reports->run($type, $from, $to);

        if ($request->query('export') === 'csv') {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, array_values($report['columns']));
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($k) => is_float($row[$k] ?? null) ? round($row[$k], 2) : ($row[$k] ?? ''), array_keys($report['columns'])));
            }
            rewind($out);
            return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$type.'-'.$from->toDateString().'-'.$to->toDateString().'.csv"']);
        }

        return view($request->query('print') ? 'admin.reports.print' : 'admin.reports.show', ['r' => $report, 'all' => collect(ReportService::TYPES)->filter(fn ($x) => $request->user()->hasPermission($x[1]))]);
    }
}
