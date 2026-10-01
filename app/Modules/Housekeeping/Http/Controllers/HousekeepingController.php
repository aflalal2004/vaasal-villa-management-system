<?php

namespace App\Modules\Housekeeping\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\Employee;
use App\Models\HkTask;
use App\Models\HkTaskItem;
use App\Models\LinenItem;
use App\Models\Villa;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Housekeeping\Services\HousekeepingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HousekeepingController extends Controller
{
    public function __construct(private HousekeepingService $hk) {}

    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $tasks = HkTask::with(['villa', 'assignee', 'items'])->where(fn ($q) => $q->where('scheduled_date', $date)->orWhereIn('status', \App\Models\HkTask::OPEN))
            ->orderByRaw("FIELD(priority,'urgent','high','normal','low')")->get();
        return view('admin.housekeeping.index', [
            'date' => $date,
            'villas' => Villa::with(['type', 'currentStay.booking.guest'])->where('is_active', true)->orderBy('sort_order')->get(),
            'tasks' => $tasks,
            'staff' => $this->staff(),
            'view' => $request->query('view', 'board'),
            'arrivalsToday' => \App\Models\BookingVilla::where('status', 'active')->where('arrival', now()->toDateString())
                ->whereHas('booking', fn ($q) => $q->whereIn('status', ['confirmed', 'tentative']))->pluck('villa_id')->flip(),
        ]);
    }

    /** Mobile-first list for room attendants: only their tasks. */
    public function my(Request $request)
    {
        $employee = $request->user()->employee;
        // Open pool: unassigned pending tasks anyone in housekeeping can accept (e.g. created by a checkout).
        $pool = HkTask::with(['villa', 'items', 'booking.guest'])->whereNull('assigned_to')->where('status', 'pending')
            ->orderByRaw("FIELD(priority,'urgent','high','normal','low')")->orderBy('created_at')->get();
        $tasks = $employee ? HkTask::with(['villa', 'items'])->where('assigned_to', $employee->id)
            ->where(fn ($q) => $q->whereIn('status', \App\Models\HkTask::OPEN)->orWhere('scheduled_date', now()->toDateString()))
            ->orderByRaw("FIELD(status,'in_progress','pending','inspection','completed','cancelled')")->orderByRaw("FIELD(priority,'urgent','high','normal','low')")->get() : collect();
        return view('admin.housekeeping.my', ['tasks' => $tasks, 'employee' => $employee, 'pool' => $pool]);
    }

    public function show(Request $request, HkTask $task)
    {
        $this->authorizeTask($request, $task);
        return view('admin.housekeeping.task', ['task' => $task->load(['villa.type', 'assignee', 'items', 'booking.guest', 'inspector', 'linen.item']),
            'linen' => LinenItem::orderBy('name')->get(), 'staff' => $this->staff()]);
    }

    public function start(Request $request, HkTask $task)
    {
        $this->authorizeTask($request, $task);
        $this->hk->start($task);
        return back()->with('success', ($task->wasChanged('paused_minutes') ? 'Cleaning resumed for ' : 'Cleaning started for ').$task->villa->code.'.');
    }

    public function accept(Request $request, HkTask $task)
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return back()->with('error', 'Your login is not linked to an employee profile. Ask the supervisor to assign the task.');
        }
        $this->hk->accept($task, $employee);
        return back()->with('success', 'Villa '.$task->villa->code.' accepted — tap Start cleaning when you arrive.');
    }

    public function pause(Request $request, HkTask $task)
    {
        $this->authorizeTask($request, $task);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:120']]);
        $this->hk->pause($task, $data['reason'] ?? null);
        return back()->with('success', 'Villa '.$task->villa->code.' paused. Resume with Start cleaning.');
    }

    public function toggle(Request $request, HkTask $task, HkTaskItem $item)
    {
        $this->authorizeTask($request, $task);
        abort_unless($item->hk_task_id === $task->id, 404);
        $this->hk->toggleItem($task, $item->id, $request->boolean('checked'));
        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function submit(Request $request, HkTask $task)
    {
        $this->authorizeTask($request, $task);
        $data = $request->validate(['linen' => ['nullable', 'array'], 'linen.*.out' => ['nullable', 'integer', 'min:0', 'max:50'], 'linen.*.in' => ['nullable', 'integer', 'min:0', 'max:50']]);
        $this->hk->requestInspection($task, $data['linen'] ?? []);
        return redirect()->route($request->user()->hasPermission('housekeeping.view') ? 'admin.housekeeping.index' : 'admin.housekeeping.my')
            ->with('success', 'Villa '.$task->villa->code.' sent for inspection.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'villa_id' => ['required', 'exists:villas,id'], 'type' => ['required', Rule::in(array_keys(HkTask::TYPES))],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'scheduled_date' => ['required', 'date'],
            'assigned_to' => ['nullable', 'exists:employees,id'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $villa = Villa::findOrFail($data['villa_id']);
        $task = $this->hk->createTask($villa, $data['type'], $data['scheduled_date'], null, $data['priority'], $data['assigned_to'] ?? null, $data['notes'] ?? null);
        if (in_array($data['type'], ['departure', 'deep'], true) && $villa->occupancy_status === 'vacant' && $villa->hk_status === 'ready') {
            $villa->update(['hk_status' => 'dirty']);
        }
        return back()->with('success', 'Task created for '.$villa->code.'.');
    }

    public function assign(Request $request, HkTask $task)
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'exists:employees,id']]);
        $this->hk->assign($task, $data['assigned_to'] ? Employee::find($data['assigned_to']) : null);
        return back()->with('success', 'Task assigned.');
    }

    public function cancel(HkTask $task)
    {
        if (in_array($task->status, ['completed', 'cancelled'], true)) {
            throw new BusinessRuleException('This task is already closed.');
        }
        $task->update(['status' => 'cancelled']);
        return back()->with('success', 'Task cancelled.');
    }

    public function approve(Request $request, HkTask $task)
    {
        $this->hk->approve($task, $request->input('notes'));
        return back()->with('success', 'Inspection passed — '.$task->villa->code.' is '.($task->villa->fresh()->hk_status === 'ready' ? 'Ready for arrival.' : 'Clean (maintenance pending).'));
    }

    public function reject(Request $request, HkTask $task)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:500']]);
        $this->hk->reject($task, $data['notes']);
        return back()->with('success', 'Sent back to the housekeeper with your notes.');
    }

    public function setStatus(Request $request, Villa $villa)
    {
        $data = $request->validate(['hk_status' => ['required', Rule::in(array_keys(Villa::HK_LABELS))], 'reason' => ['nullable', 'string', 'max:200']]);
        $this->hk->setVillaStatus($villa, $data['hk_status'], $data['reason'] ?? null);
        return back()->with('success', $villa->code.' set to '.Villa::HK_LABELS[$data['hk_status']].'.');
    }

    public function stayovers()
    {
        $n = $this->hk->generateStayovers(now());
        return back()->with('success', $n.' stay-over task(s) created for occupied villas.');
    }

    public function linen()
    {
        return view('admin.housekeeping.linen', [
            'items' => LinenItem::orderBy('name')->get(),
            'recent' => \App\Models\LinenMovement::with(['item', 'task.villa'])->latest()->limit(30)->get(),
            'villaCount' => Villa::where('is_active', true)->count(),
        ]);
    }

    public function saveLinen(Request $request)
    {
        $data = $request->validate([
            'items' => ['array'], 'items.*.par_per_villa' => ['required', 'integer', 'min:0'], 'items.*.stock_qty' => ['required', 'integer', 'min:0'],
            'items.*.laundry_return' => ['nullable', 'integer', 'min:0'],
            'new_name' => ['nullable', 'string', 'max:80', 'unique:linen_items,name'], 'new_par' => ['nullable', 'integer', 'min:0'], 'new_stock' => ['nullable', 'integer', 'min:0'],
        ]);
        foreach ($data['items'] ?? [] as $id => $row) {
            $item = LinenItem::findOrFail($id);
            $return = min((int) ($row['laundry_return'] ?? 0), $item->in_laundry_qty);
            $item->update(['par_per_villa' => $row['par_per_villa'], 'stock_qty' => $row['stock_qty'] + $return, 'in_laundry_qty' => $item->in_laundry_qty - $return]);
        }
        if (! empty($data['new_name'])) {
            LinenItem::create(['name' => $data['new_name'], 'par_per_villa' => $data['new_par'] ?? 2, 'stock_qty' => $data['new_stock'] ?? 0]);
        }
        return back()->with('success', 'Linen stock saved.');
    }

    public function checklists()
    {
        return view('admin.housekeeping.checklists', ['templates' => ChecklistTemplate::orderBy('task_type')->get()]);
    }

    public function saveChecklist(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:checklist_templates,id'], 'name' => ['required', 'string', 'max:100'],
            'task_type' => ['required', Rule::in(array_keys(HkTask::TYPES))], 'items' => ['required', 'string', 'max:5000'],
        ]);
        $items = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $data['items']))));
        ChecklistTemplate::updateOrCreate(['id' => $data['id'] ?? null], ['name' => $data['name'], 'task_type' => $data['task_type'], 'items' => $items, 'is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Checklist saved. New tasks will use it.');
    }

    private function staff()
    {
        return Employee::whereHas('department', fn ($q) => $q->where('code', 'HK'))->where('status', 'active')->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->fullName()]);
    }

    /** Room attendants can only act on their own tasks; supervisors on all. */
    private function authorizeTask(Request $request, HkTask $task): void
    {
        $u = $request->user();
        if ($u->hasPermission('housekeeping.manage|housekeeping.inspect|housekeeping.view')) return;
        abort_unless($u->employee && $task->assigned_to === $u->employee->id, 403, 'This task is assigned to someone else.');
    }
}
