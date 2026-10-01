<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\DebtReminderLog;
use App\Services\DebtReminderService;
use Illuminate\Http\Request;

class DebtController extends CrudController
{
    protected string $model = Debt::class;
    protected string $routeName = 'debts';
    protected string $title = 'Debt';
    protected string $icon = 'fa-solid fa-hand-holding-dollar';
    protected string $accent = 'amber';

    protected array $fields = [
        ['tab'=>'Details','name'=>'type','label'=>'Type','type'=>'select','required'=>true,'options'=>[
            'borrowed'=>'Borrowed (I owe them)','lent'=>'Lent (they owe me)',
        ]],
        ['tab'=>'Details','name'=>'person_name','label'=>'Borrower / Lender','type'=>'text','required'=>true],
        ['tab'=>'Contact & Notes','name'=>'contact_email','label'=>'Email','type'=>'text'],
        ['tab'=>'Contact & Notes','name'=>'contact_phone','label'=>'Phone / SMS number','type'=>'text'],
        ['tab'=>'Details','name'=>'amount','label'=>'Outstanding Amount','type'=>'number','required'=>true,'money'=>true],
        ['tab'=>'Details','name'=>'date','label'=>'Date','type'=>'date','required'=>true],
        ['tab'=>'Details','name'=>'due_date','label'=>'Due Date','type'=>'date'],
        ['tab'=>'Details','name'=>'status','label'=>'Status','type'=>'select','required'=>true,'options'=>[
            'outstanding'=>'Outstanding','overdue'=>'Overdue','paid'=>'Paid',
        ]],
        ['tab'=>'Contact & Notes','name'=>'notes','label'=>'Notes','type'=>'textarea'],
        ['tab'=>'Reminders','name'=>'reminder_enabled','label'=>'Automatic reminders','type'=>'select','options'=>['1'=>'Enabled','0'=>'Disabled']],
        ['tab'=>'Reminders','name'=>'reminder_channel','label'=>'Reminder channel','type'=>'select','options'=>[
            'email'=>'Email only','sms'=>'SMS only','both'=>'Email + SMS',
        ]],
        ['tab'=>'Reminders','name'=>'reminder_frequency','label'=>'Frequency','type'=>'select','options'=>[
            'once'=>'Once','daily'=>'Daily','every_3_days'=>'Every 3 days','weekly'=>'Weekly',
            'fortnightly'=>'Every 2 weeks','monthly'=>'Monthly',
        ]],
        ['tab'=>'Reminders','name'=>'next_reminder_at','label'=>'Next reminder','type'=>'datetime-local'],
    ];

    protected array $rules = [
        'type'=>'required|in:borrowed,lent','person_name'=>'required|string|max:255',
        'contact_email'=>'nullable|email|max:255','contact_phone'=>'nullable|string|max:40',
        'amount'=>'required|numeric|min:0.01','date'=>'required|date','due_date'=>'nullable|date',
        'status'=>'required|in:outstanding,overdue,paid','notes'=>'nullable|string',
        'reminder_enabled'=>'nullable|boolean','reminder_channel'=>'nullable|in:email,sms,both',
        'reminder_frequency'=>'nullable|in:once,daily,every_3_days,weekly,fortnightly,monthly',
        'next_reminder_at'=>'nullable|date',
    ];

    public function store(Request $request)
    {
        $this->normaliseSaveRequest($request);

        return parent::store($request);
    }

    public function update(Request $request, int $id)
    {
        $this->normaliseSaveRequest($request);

        return parent::update($request, $id);
    }

    private function normaliseSaveRequest(Request $request): void
    {
        $timezone = $request->user()->timezone
            ?: config('app.timezone', 'Africa/Kampala');

        $status = strtolower(trim((string) $request->input('status', 'outstanding')));

        if (in_array($status, ['active', 'pending', 'open'], true)) {
            $status = 'outstanding';
        }

        $type = strtolower(trim((string) (
            $request->input('type')
            ?? $request->input('debt_type')
            ?? 'borrowed'
        )));

        $request->merge([
            'reminder_enabled' => $request->boolean('reminder_enabled'),
            'type' => in_array($type, ['borrowed', 'lent'], true)
                ? $type
                : 'borrowed',
            'person_name' => trim((string) (
                $request->input('person_name')
                ?? $request->input('name')
                ?? ''
            )),
            'date' => $request->input('date')
                ?: now($timezone)->toDateString(),
            'status' => in_array($status, ['outstanding', 'overdue', 'paid'], true)
                ? $status
                : 'outstanding',
        ]);
    }

    public function index(Request $request)
    {
        $this->syncOverdueStatuses($request);

        $query = Debt::where('user_id', $request->user()->id);

        return $this->renderIndex($request, $query, [
            // Pass statistics explicitly so the generic CRUD view always
            // receives the current-month and outstanding debt cards.
            'stats' => $this->stats($request),
            'reminderLogs' => DebtReminderLog::where('user_id', $request->user()->id)
                ->with('debt:id,person_name')
                ->latest()
                ->limit(50)
                ->get(),
            'smsReady' => \App\Models\SmsProvider::where('is_enabled', true)->exists(),
        ]);
    }

    public function remindersPage(Request $request)
    {
        return view('debts.reminders', [
            'debts' => Debt::where('user_id', $request->user()->id)
                ->where('status', 'outstanding')
                ->where('is_archived', false)
                ->orderBy('due_date')
                ->get(),
        ]);
    }

    public function sendReminder(Request $request, Debt $debt, DebtReminderService $service)
    {
        abort_unless((int) $debt->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'channel' => ['required','in:email,sms,both'],
            'recipient_scope' => ['required','in:counterparty,me,both'],
            'message' => ['nullable','string','max:1200'],
        ]);

        $results = $service->send(
            $debt,
            $request->user(),
            $data['channel'],
            $data['recipient_scope'],
            $data['message'] ?? null
        );

        $sent = collect($results)->where('status', 'sent')->count();

        return back()->with(
            $sent > 0 ? 'success' : 'error',
            $sent > 0 ? "Reminder sent through {$sent} channel(s)." : 'The reminder could not be delivered. Check the recipient details and provider settings.'
        );
    }

    public function reminderHistory(Request $request, Debt $debt)
    {
        abort_unless((int) $debt->user_id === (int) $request->user()->id, 403);

        return response()->json([
            'data' => $debt->reminderLogs()->latest()->paginate(50),
        ]);
    }

    private function syncOverdueStatuses(Request $request): void
    {
        $userId = (int) $request->user()->id;

        Debt::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['outstanding', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->update(['status' => 'overdue']);

        Debt::query()
            ->where('user_id', $userId)
            ->where('status', 'overdue')
            ->where(function ($query): void {
                $query->whereNull('due_date')
                    ->orWhereDate('due_date', '>=', today());
            })
            ->update(['status' => 'outstanding']);
    }

    protected function stats(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $outstanding = Debt::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['outstanding', 'overdue']);

        if (\Illuminate\Support\Facades\Schema::hasColumn('debts', 'is_archived')) {
            $outstanding->where('is_archived', false);
        }

        $currentMonth = Debt::query()
            ->where('user_id', $userId)
            ->whereBetween('date', [$monthStart, $monthEnd]);

        if (\Illuminate\Support\Facades\Schema::hasColumn('debts', 'is_archived')) {
            $currentMonth->where('is_archived', false);
        }

        $outstandingBorrowed = (float) (clone $outstanding)->where('type', 'borrowed')->sum('amount');
        $outstandingLent = (float) (clone $outstanding)->where('type', 'lent')->sum('amount');
        $monthBorrowed = (float) (clone $currentMonth)->where('type', 'borrowed')->sum('amount');
        $monthLent = (float) (clone $currentMonth)->where('type', 'lent')->sum('amount');

        return [
            ['label' => now()->format('M Y').' Borrowed', 'value' => format_money($monthBorrowed), 'icon' => 'fa-solid fa-arrow-down', 'color' => 'rose'],
            ['label' => now()->format('M Y').' Lent', 'value' => format_money($monthLent), 'icon' => 'fa-solid fa-arrow-up', 'color' => 'emerald'],
            ['label' => 'Outstanding Borrowed', 'value' => format_money($outstandingBorrowed), 'icon' => 'fa-solid fa-arrow-trend-down', 'color' => 'amber'],
            ['label' => 'Outstanding Lent', 'value' => format_money($outstandingLent), 'icon' => 'fa-solid fa-arrow-trend-up', 'color' => 'sky'],
        ];
    }
}

