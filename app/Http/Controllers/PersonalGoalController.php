<?php
namespace App\Http\Controllers;

use App\Models\PersonalGoal;
use App\Services\PersonalHealthGoalProgressService;
use Illuminate\Http\Request;

class PersonalGoalController extends CrudController
{
    protected string $model = PersonalGoal::class;
    protected string $routeName = 'personal-goals';
    protected string $title = 'Goal';
    protected string $icon = 'fa-solid fa-bullseye';
    protected string $accent = 'violet';
    protected string $dateField = 'target_date';

    protected array $fields = [
        ['tab'=>'Goal','table'=>'meta','name'=>'module','label'=>'Life Area','type'=>'select','required'=>true,'options'=>[
            'finance'=>'Finance','savings'=>'Savings','education'=>'Education','spiritual'=>'Spiritual Growth','health'=>'Health & Self-care','exercise'=>'Exercise & Fitness','diet'=>'Diet & Nutrition','productivity'=>'Productivity','projects'=>'Projects','personal'=>'Personal Development'
        ]],
        ['tab'=>'Goal','name'=>'title','label'=>'Goal','type'=>'text','required'=>true],
        ['tab'=>'Goal','name'=>'description','label'=>'Why this matters','type'=>'textarea'],
        ['tab'=>'Progress','name'=>'start_date','label'=>'Start Date','type'=>'date'],
        ['tab'=>'Progress','name'=>'target_date','label'=>'Target Date','type'=>'date'],
        ['tab'=>'Progress','name'=>'target_value','label'=>'Target Value (optional)','type'=>'number'],
        ['tab'=>'Progress','name'=>'current_value','label'=>'Current Value (optional)','type'=>'number', 'placeholder'=>'Auto-filled for health goals'],
        ['tab'=>'Progress','table'=>'meta','name'=>'progress_percent','label'=>'Progress %','type'=>'number','required'=>true, 'placeholder'=>'0–100 (auto for health goals)'],
        ['tab'=>'Goal','name'=>'status','label'=>'Status','type'=>'select','required'=>true,'options'=>['not_started'=>'Not started','in_progress'=>'In progress','completed'=>'Completed','paused'=>'Paused']],
        ['tab'=>'Goal','table'=>'meta','name'=>'priority','label'=>'Priority','type'=>'select','required'=>true,'options'=>['low'=>'Low','medium'=>'Medium','high'=>'High']],
        ['tab'=>'Reminder & Notes','name'=>'reminder_at','label'=>'Reminder Date & Time','type'=>'datetime-local'],
        ['tab'=>'Reminder & Notes','name'=>'notes','label'=>'Notes','type'=>'textarea'],
    ];

    protected array $rules = [
        'module'=>'required|in:finance,savings,education,spiritual,health,exercise,diet,productivity,projects,personal',
        'title'=>'required|string|max:255','description'=>'nullable|string','start_date'=>'nullable|date','target_date'=>'nullable|date|after_or_equal:start_date',
        'target_value'=>'nullable|numeric|min:0','current_value'=>'nullable|numeric|min:0','progress_percent'=>'required|integer|min:0|max:100',
        'status'=>'required|in:not_started,in_progress,completed,paused','priority'=>'required|in:low,medium,high','reminder_at'=>'nullable|date','notes'=>'nullable|string'
    ];

    public function store(Request $request)
    {
        if ($request->input('module')==='health') {
            $request->merge(['current_value'=>0,'progress_percent'=>0]);
        }

        $response=parent::store($request);

        if ($request->input('module')==='health') {
            app(PersonalHealthGoalProgressService::class)->sync($request->user());
        }

        return $response;
    }

    public function update(Request $request, int $id)
    {
        $existing=PersonalGoal::where('user_id',$request->user()->id)->findOrFail($id);

        if ($request->input('module',$existing->module)==='health') {
            $request->merge([
                'current_value'=>$existing->current_value??0,
                'progress_percent'=>$existing->progress_percent??0,
            ]);
        }

        $response=parent::update($request,$id);

        if ($request->input('module',$existing->module)==='health') {
            app(PersonalHealthGoalProgressService::class)->sync($request->user());
        }

        return $response;
    }

    public function index(Request $request)
    {
        try {
            app(PersonalHealthGoalProgressService::class)->sync($request->user());
        } catch (\Throwable $exception) {
            report($exception);
        }

        $query = PersonalGoal::where('user_id', $request->user()->id);
        if ($request->filled('module')) $query->where('module', $request->string('module')->toString());
        return $this->renderIndex($request, $query, ['selectedModule'=>$request->query('module')], fn($q)=>$q->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")->orderBy('target_date'));
    }

    protected function stats(Request $request): array
    {
        $q = PersonalGoal::where('user_id',$request->user()->id)->where('is_archived',false);
        if ($request->filled('module')) $q->where('module',$request->query('module'));
        return [
            ['label'=>'Active','value'=>(string)(clone $q)->whereIn('status',['not_started','in_progress'])->count(),'icon'=>'fa-solid fa-bullseye','color'=>'violet'],
            ['label'=>'Completed','value'=>(string)(clone $q)->where('status','completed')->count(),'icon'=>'fa-solid fa-circle-check','color'=>'emerald'],
            ['label'=>'Average progress','value'=>((int)round((clone $q)->avg('progress_percent') ?? 0)).'%','icon'=>'fa-solid fa-chart-line','color'=>'sky'],
        ];
    }

    protected function nudge(Request $request): ?string
    {
        $soonest = PersonalGoal::where('user_id', $request->user()->id)
            ->where('is_archived', false)
            ->whereIn('status', ['not_started', 'in_progress'])
            ->whereNotNull('target_date')
            ->where('target_date', '>=', now()->toDateString())
            ->orderBy('target_date')
            ->first(['title', 'target_date', 'progress_percent']);

        if (! $soonest) {
            return 'Set a target date on a goal to see what to focus on next.';
        }

        $days = (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($soonest->target_date)->startOfDay());

        return 'Next target: ' . \Illuminate\Support\Str::limit((string) $soonest->title, 40)
            . ' · ' . (int) $soonest->progress_percent . '% done, due '
            . ($days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days"));
    }
}
