<?php

namespace App\Http\Controllers;

use App\Models\SavingsGoal;
use Illuminate\Http\Request;

class SavingsGoalController extends CrudController
{
    protected string $model = SavingsGoal::class;
    protected string $routeName = 'savings-goals';
    protected string $title = 'Savings Goal';
    protected string $icon = 'fa-solid fa-piggy-bank';
    protected string $accent = 'green';

    protected array $fields = [
        ['tab'=>'Goal','name'=>'name','label'=>'Goal Name','type'=>'text','required'=>true],
        ['tab'=>'Goal','name'=>'target_amount','label'=>'Target Amount','type'=>'number','required'=>true,'money'=>true],
        ['tab'=>'Goal','name'=>'target_date','label'=>'Target Date','type'=>'date'],
        ['tab'=>'Goal','name'=>'status','label'=>'Status','type'=>'select','required'=>true,'options'=>[
            'in_progress'=>'In Progress','completed'=>'Completed','paused'=>'Paused',
        ]],
        ['tab'=>'Goal','name'=>'notes','label'=>'Notes','type'=>'textarea'],
        ['tab'=>'Reminders','name'=>'reminder_enabled','label'=>'Contribution reminders','type'=>'select','options'=>['1'=>'Enabled','0'=>'Disabled']],
        ['tab'=>'Reminders','name'=>'reminder_channel','label'=>'Reminder channel','type'=>'select','options'=>[
            'email'=>'Email','sms'=>'SMS','both'=>'Email + SMS',
        ]],
        ['tab'=>'Reminders','name'=>'reminder_frequency','label'=>'Reminder frequency','type'=>'select','options'=>[
            'daily'=>'Daily','weekly'=>'Weekly','fortnightly'=>'Every 2 weeks','monthly'=>'Monthly',
        ]],
        ['tab'=>'Reminders','name'=>'next_reminder_at','label'=>'Next reminder','type'=>'datetime-local'],
    ];

    protected array $rules = [
        'name'=>'required|string|max:255','target_amount'=>'required|numeric|min:0.01',
        'target_date'=>'nullable|date','status'=>'required|in:in_progress,completed,paused',
        'notes'=>'nullable|string','reminder_enabled'=>'nullable|boolean',
        'reminder_channel'=>'nullable|in:email,sms,both',
        'reminder_frequency'=>'nullable|in:daily,weekly,fortnightly,monthly',
        'next_reminder_at'=>'nullable|date',
    ];

    public function index(Request $request)
    {
        return $this->renderIndex(
            $request,
            SavingsGoal::where('user_id', $request->user()->id)->withSum(
                ['contributions as saved_amount' => fn ($q) => $q->where('is_archived', false)],
                'amount'
            ),
            ['showSavingsProgress' => true]
        );
    }

    protected function stats(Request $request): array
    {
        $goals = SavingsGoal::where('user_id', $request->user()->id)
            ->where('is_archived', false)->withSum('contributions','amount')->get();

        $saved = (float) $goals->sum('contributions_sum_amount');
        $target = (float) $goals->sum('target_amount');
        $remaining = max(0, $target - $saved);
        $progress = $target > 0 ? round(($saved / $target) * 100, 1) : 0;

        return [
            ['label'=>'Total saved','value'=>format_money($saved),'icon'=>'fa-solid fa-piggy-bank','color'=>'green'],
            ['label'=>'Remaining','value'=>format_money($remaining),'icon'=>'fa-solid fa-road','color'=>'amber'],
            ['label'=>'Overall progress','value'=>$progress.'%','icon'=>'fa-solid fa-chart-line','color'=>'emerald'],
            ['label'=>'Active goals','value'=>(string) $goals->where('status','in_progress')->count(),'icon'=>'fa-solid fa-bullseye','color'=>'blue'],
        ];
    }
}
