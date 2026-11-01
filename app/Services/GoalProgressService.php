<?php

namespace App\Services;

use App\Models\PersonalGoal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class GoalProgressService
{
    public function snapshot(PersonalGoal $goal): array
    {
        $goal->loadMissing(['annualPlans','dailyTasks','projectTasks']);
        if (Schema::hasTable('goal_milestones')) $goal->loadMissing('milestones');

        $components = [];

        $valueProgress = null;
        if ($goal->target_value !== null && (float)$goal->target_value > 0 && $goal->current_value !== null) {
            $valueProgress = min(100, max(0, (int) round(((float)$goal->current_value / (float)$goal->target_value) * 100)));
            $components['value'] = $valueProgress;
        }

        $milestones = Schema::hasTable('goal_milestones') ? $goal->milestones : collect();
        if ($milestones->isNotEmpty()) {
            $weighted = $milestones->filter(fn($m) => (int)($m->weight ?? 0) > 0);
            if ($weighted->isNotEmpty()) {
                $totalWeight = max(1, (int)$weighted->sum('weight'));
                $doneWeight = (int)$weighted->where('status','completed')->sum('weight');
                $components['milestones'] = min(100, (int) round(($doneWeight / $totalWeight) * 100));
            } else {
                $components['milestones'] = (int) round(($milestones->where('status','completed')->count() / $milestones->count()) * 100);
            }
        }

        $tasks = $goal->dailyTasks->count() + $goal->projectTasks->count();
        $tasksDone = $goal->dailyTasks->where('is_completed', true)->count() + $goal->projectTasks->where('status', 'done')->count();
        if ($tasks > 0) $components['tasks'] = (int) round(($tasksDone / $tasks) * 100);

        if ($goal->annualPlans->isNotEmpty()) {
            $components['plans'] = (int) round($goal->annualPlans->avg(fn($p) => $p->status === 'completed' ? 100 : (int)($p->progress_percent ?? 0)));
        }

        if ($valueProgress !== null) {
            // A target/current value pair is the clearest signal of progress, so
            // it drives the percentage directly rather than being averaged with
            // milestones/tasks/plans (which would otherwise dilute it — e.g. a
            // goal at 50% of its target but with two unfinished tasks used to
            // read ~17%).
            $automatic = $valueProgress;
        } elseif ($components) {
            $automatic = (int) round(array_sum($components) / count($components));
        } else {
            $automatic = (int) ($goal->progress_percent ?? 0);
        }
        $automatic = min(100, max(0, $automatic));

        return [
            'progress' => $automatic,
            'components' => $components,
            'milestones_total' => $milestones->count(),
            'milestones_completed' => $milestones->where('status','completed')->count(),
            'tasks_total' => $tasks,
            'tasks_completed' => $tasksDone,
            'plans_total' => $goal->annualPlans->count(),
        ];
    }

    public function recalculate(PersonalGoal|int|null $goal): ?PersonalGoal
    {
        if (!$goal) return null;
        $goal = $goal instanceof PersonalGoal ? $goal->fresh() : PersonalGoal::find($goal);
        if (!$goal) return null;

        $snapshot = $this->snapshot($goal);
        $progress = (int)$snapshot['progress'];
        $status = $goal->status;
        if ($progress >= 100) $status = 'completed';
        elseif ($status === 'completed' && $progress < 100) $status = 'in_progress';
        elseif ($progress > 0 && $status === 'not_started') $status = 'in_progress';

        if ((int)$goal->progress_percent !== $progress || $goal->status !== $status) {
            PersonalGoal::withoutEvents(function () use ($goal, $progress, $status) {
                $goal->forceFill(['progress_percent'=>$progress, 'status'=>$status])->save();
            });
        }
        return $goal->fresh();
    }

    public function accountability(PersonalGoal $goal, ?User $user = null): array
    {
        $user ??= $goal->user;
        $tz = $user?->timezone ?: 'Africa/Kampala';
        $today = Carbon::now($tz)->startOfDay();
        $snapshot = $this->snapshot($goal);
        $progress = (int) ($snapshot['progress'] ?? 0);

        $start = $goal->start_date?->copy()->startOfDay();
        $target = $goal->target_date?->copy()->startOfDay();
        $expected = null;
        if ($start && $target && $target->gt($start)) {
            $totalDays = max(1, $start->diffInDays($target));
            $elapsed = min($totalDays, max(0, $start->diffInDays($today, false)));
            $expected = (int) round(($elapsed / $totalDays) * 100);
        }

        $status = 'on_track';
        if ($progress >= 100) $status = 'completed';
        elseif ($target && $target->lt($today)) $status = 'overdue';
        elseif ($expected !== null && $progress >= min(100, $expected + 12)) $status = 'ahead';
        elseif ($expected !== null && $progress + 12 < $expected) $status = 'at_risk';
        elseif ($target && $today->diffInDays($target, false) <= 14 && $progress < 80) $status = 'at_risk';

        $goal->loadMissing('milestones');
        $overdue = $goal->milestones
            ->filter(fn($m) => $m->status !== 'completed' && $m->target_date && $m->target_date->lt($today))
            ->map(function ($m) use ($today, $target) {
                $suggested = $today->copy()->addDays(7);
                if ($target && $suggested->gt($target)) $suggested = $target->copy();
                if ($suggested->lt($today)) $suggested = $today->copy()->addDay();
                return [
                    'id'=>$m->id,'title'=>$m->title,'target_date'=>$m->target_date?->toDateString(),
                    'days_overdue'=>abs($m->target_date->diffInDays($today, false)),
                    'suggested_date'=>$suggested->toDateString(),
                ];
            })->values();

        $weekStart = Carbon::now($tz)->startOfWeek()->toDateString();
        $checkin = null;
        if (Schema::hasTable('goal_checkins')) {
            $checkin = $goal->checkins()->whereDate('week_start', $weekStart)->first();
        }

        $message = match ($status) {
            'ahead' => 'You are ahead of the pace needed for this goal. Keep the rhythm without overloading your week.',
            'at_risk' => 'This goal is slipping behind its expected pace. Reschedule missed milestones and protect one realistic action this week.',
            'overdue' => 'The goal target date has passed. Review the deadline and choose a realistic recovery plan.',
            'completed' => 'Goal completed. Capture what worked so you can repeat it on your next goal.',
            default => 'You are broadly on track. Keep completing the next linked action or milestone.',
        };

        return [
            'status'=>$status,
            'status_label'=>str($status)->replace('_',' ')->title()->toString(),
            'progress'=>$progress,
            'expected_progress'=>$expected,
            'variance'=>$expected === null ? null : $progress - $expected,
            'message'=>$message,
            'overdue_milestones'=>$overdue->all(),
            'overdue_count'=>$overdue->count(),
            'week_start'=>$weekStart,
            'checkin'=>$checkin ? $checkin->only(['id','week_start','confidence','status','planned_action','note','updated_at']) : null,
        ];
    }

    public function weeklyMovement(PersonalGoal $goal, ?User $user = null): array
    {
        $user ??= $goal->user;
        $tz = $user?->timezone ?: 'Africa/Kampala';
        $start = Carbon::now($tz)->startOfWeek();
        $end = Carbon::now($tz)->endOfWeek();

        $goal->loadMissing(['dailyTasks','projectTasks','annualPlans']);
        if (Schema::hasTable('goal_milestones')) $goal->loadMissing('milestones');

        $dailyDone = $goal->dailyTasks->filter(fn($t) => $t->is_completed && $t->completed_at && $t->completed_at->between($start, $end));
        $projectDone = $goal->projectTasks->filter(fn($t) => $t->status === 'done' && $t->updated_at && $t->updated_at->between($start, $end));
        $milestonesDone = Schema::hasTable('goal_milestones')
            ? $goal->milestones->filter(fn($m) => $m->status === 'completed' && $m->completed_at && $m->completed_at->between($start, $end))
            : collect();
        $plansMoved = $goal->annualPlans->filter(fn($p) => $p->updated_at && $p->updated_at->between($start, $end));

        $items = collect();
        $dailyDone->each(fn($t) => $items->push(['type'=>'Daily task','title'=>$t->title,'date'=>$t->completed_at?->toDateString()]));
        $projectDone->each(fn($t) => $items->push(['type'=>'Project task','title'=>$t->title,'date'=>$t->updated_at?->toDateString()]));
        $milestonesDone->each(fn($m) => $items->push(['type'=>'Milestone','title'=>$m->title,'date'=>$m->completed_at?->toDateString()]));
        $plansMoved->take(3)->each(fn($p) => $items->push(['type'=>'Annual plan','title'=>$p->title,'date'=>$p->updated_at?->toDateString()]));

        return [
            'week_start' => $start->toDateString(),
            'week_end' => $end->toDateString(),
            'completed_daily_tasks' => $dailyDone->count(),
            'completed_project_tasks' => $projectDone->count(),
            'completed_milestones' => $milestonesDone->count(),
            'plans_touched' => $plansMoved->count(),
            'movement_count' => $items->count(),
            'items' => $items->take(8)->values()->all(),
            'message' => $items->isEmpty()
                ? 'Nothing moved this goal yet this week. Choose one small linked action to create momentum.'
                : 'You moved this goal forward with '.$items->count().' meaningful action'.($items->count() === 1 ? '' : 's').' this week.',
        ];
    }
    public function learning(PersonalGoal $goal): array
    {
        if (!Schema::hasTable('goal_reflections')) {
            return ['count'=>0,'latest'=>null,'patterns'=>[],'prompt'=>''];
        }

        $goal->loadMissing(['reflections.milestone']);
        $reflections = $goal->reflections->take(8);
        $latest = $reflections->first();

        $patterns = collect([
            'what_worked' => $reflections->pluck('what_worked'),
            'challenges' => $reflections->pluck('challenges'),
            'lessons' => $reflections->pluck('lessons_learned'),
            'repeat_next_time' => $reflections->pluck('repeat_next_time'),
            'change_next_time' => $reflections->pluck('change_next_time'),
        ])->map(fn($items) => $items->filter(fn($v) => is_string($v) && trim($v) !== '')->take(4)->values()->all())->all();

        $promptParts = [];
        foreach ($reflections->take(5) as $reflection) {
            $scope = $reflection->milestone?->title ? 'Milestone: '.$reflection->milestone->title : 'Goal reflection';
            $promptParts[] = [
                'scope' => $scope,
                'what_worked' => $reflection->what_worked,
                'challenges' => $reflection->challenges,
                'lessons_learned' => $reflection->lessons_learned,
                'repeat_next_time' => $reflection->repeat_next_time,
                'change_next_time' => $reflection->change_next_time,
            ];
        }

        return [
            'count' => $reflections->count(),
            'latest' => $latest ? [
                'id'=>$latest->id,
                'reflection_type'=>$latest->reflection_type,
                'milestone_title'=>$latest->milestone?->title,
                'what_worked'=>$latest->what_worked,
                'challenges'=>$latest->challenges,
                'lessons_learned'=>$latest->lessons_learned,
                'repeat_next_time'=>$latest->repeat_next_time,
                'change_next_time'=>$latest->change_next_time,
                'confidence_after'=>$latest->confidence_after,
                'reflected_on'=>$latest->reflected_on?->toDateString(),
            ] : null,
            'patterns' => $patterns,
            'prompt' => $promptParts,
        ];
    }

}
