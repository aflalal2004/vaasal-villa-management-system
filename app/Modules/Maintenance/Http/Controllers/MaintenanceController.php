<?php

namespace App\Modules\Maintenance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MaintenanceTicket;
use App\Models\Villa;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\UploadService;
use App\Modules\Maintenance\Services\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $maintenance) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'open_all');
        $q = MaintenanceTicket::with(['villa', 'assignee', 'reporter'])
            ->when($status === 'open_all', fn ($w) => $w->whereNotIn('status', ['resolved', 'closed']))
            ->when($status !== 'open_all' && $status !== 'all', fn ($w) => $w->where('status', $status))
            ->when($request->filled('villa'), fn ($w) => $w->where('villa_id', $request->query('villa')))
            ->orderByRaw("FIELD(severity,'blocking','high','medium','low')")->latest();
        if (! $request->user()->hasPermission('maintenance.view')) {
            $q->where('reported_by', $request->user()->id);
        }
        return view('admin.maintenance.index', ['tickets' => $q->paginate(25)->withQueryString(), 'status' => $status, 'villas' => Villa::orderBy('sort_order')->pluck('code', 'id')]);
    }

    public function create(Request $request)
    {
        return view('admin.maintenance.form', ['villas' => Villa::orderBy('sort_order')->get()->mapWithKeys(fn ($v) => [$v->id => $v->code.' — '.$v->name]),
            'villaId' => $request->query('villa_id'), 'staff' => $this->technicians()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'villa_id' => ['nullable', 'exists:villas,id'],
            'location' => ['nullable', 'required_without:villa_id', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in(array_keys(MaintenanceTicket::CATEGORIES))],
            'severity' => ['required', Rule::in(array_keys(MaintenanceTicket::SEVERITIES))],
            'assigned_to' => ['nullable', 'exists:employees,id'],
            'block_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'photo' => ['nullable', 'file', 'max:'.config('vaasal.security.upload_max_kb')],
        ]);
        $blockDays = (int) ($data['block_days'] ?? 1);
        unset($data['photo'], $data['block_days']);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = UploadService::image($request->file('photo'), 'maintenance');
        }
        if (! $request->user()->hasPermission('maintenance.manage')) {
            unset($data['assigned_to']);
        }
        $ticket = $this->maintenance->report($data, $blockDays);
        return redirect()->route('admin.maintenance.show', $ticket)->with('success', 'Ticket '.$ticket->ticket_no.' created.'.($ticket->blocks_inventory ? ' The villa is out of order and removed from sale.' : ''));
    }

    public function show(MaintenanceTicket $ticket)
    {
        return view('admin.maintenance.show', ['t' => $ticket->load(['villa', 'assignee', 'reporter', 'block']), 'staff' => $this->technicians(),
            'history' => \App\Models\AuditLog::with('user')->where('auditable_type', 'MaintenanceTicket')->where('auditable_id', $ticket->id)->latest('id')->get()]);
    }

    public function status(Request $request, MaintenanceTicket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(MaintenanceTicket::STATUSES))],
            'resolution_notes' => ['nullable', 'required_if:status,resolved', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->maintenance->updateStatus($ticket, $data['status'], $data['resolution_notes'] ?? null, isset($data['cost']) ? (float) $data['cost'] : null);
        return back()->with('success', 'Ticket updated to '.MaintenanceTicket::STATUSES[$data['status']].'.');
    }

    public function assign(Request $request, MaintenanceTicket $ticket)
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'exists:employees,id']]);
        $ticket->assigned_to = $data['assigned_to'];
        AuditService::logChanges('maintenance', $ticket, 'assigned');
        $ticket->save();
        return back()->with('success', 'Ticket assigned.');
    }

    private function technicians()
    {
        return Employee::whereHas('department', fn ($q) => $q->where('code', 'MNT'))->where('status', 'active')->get()->mapWithKeys(fn ($e) => [$e->id => $e->fullName()]);
    }
}
