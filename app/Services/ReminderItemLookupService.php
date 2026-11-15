<?php

namespace App\Services;

use App\Models\DietLog;
use App\Models\EducationPlan;
use App\Models\Expense;
use App\Models\HealthCheckup;
use App\Models\Income;
use App\Models\Meeting;
use App\Models\NetworkContact;
use App\Models\Plan;
use App\Models\PersonalRelationship;
use App\Models\Project;
use App\Models\SleepLog;
use App\Models\SpiritualPractice;

/**
 * Powers the "select specific items from the related module" feature on
 * the Reminder form - given a module key (the same ones already used by
 * Reminder::module, e.g. 'plan', 'meeting'), returns that user's actual
 * records as {id, label, datetime} options for a multi-select, and knows
 * which field on each different model actually represents "its own
 * date/time" for the overlap check in ReminderController.
 *
 * 'budget' and 'custom' aren't included - budgets don't have a single
 * relevant date/time of their own, and 'custom' is free text with nothing
 * to link to.
 */
class ReminderItemLookupService
{
    /**
     * @return array<int, array{id: int, label: string, datetime: ?string}>
     */
    public function optionsFor(string $module, int $userId): array
    {
        return match ($module) {
            'plan' => Plan::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($p) => ['id' => $p->id, 'label' => $p->title, 'datetime' => optional($p->target_date)->toIso8601String()])
                ->all(),

            'income' => Income::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($i) => ['id' => $i->id, 'label' => $i->source . ' (' . format_money($i->amount) . ')', 'datetime' => optional($i->received_at)->toIso8601String()])
                ->all(),

            'expense' => Expense::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($e) => ['id' => $e->id, 'label' => $e->category . ' (' . format_money($e->amount) . ')', 'datetime' => optional($e->spent_at)->toIso8601String()])
                ->all(),

            'diet' => DietLog::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($d) => ['id' => $d->id, 'label' => \Illuminate\Support\Str::limit($d->food_items, 40), 'datetime' => optional($d->logged_at)->toIso8601String()])
                ->all(),

            'sleep' => SleepLog::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($s) => ['id' => $s->id, 'label' => 'Sleep - ' . optional($s->sleep_date)->format('Y-m-d'), 'datetime' => optional($s->sleep_date)->toIso8601String()])
                ->all(),

            'health' => HealthCheckup::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($h) => ['id' => $h->id, 'label' => $h->checkup_type, 'datetime' => optional($h->next_due_date)->toIso8601String()])
                ->all(),

            'project' => Project::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($p) => ['id' => $p->id, 'label' => $p->name, 'datetime' => optional($p->deadline)->toIso8601String()])
                ->all(),

            'meeting' => Meeting::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($m) => ['id' => $m->id, 'label' => $m->title . ' - ' . $m->start_at->format('M j, g:ia'), 'datetime' => $m->start_at->toIso8601String()])
                ->all(),

            // Personal Life
            'education' => EducationPlan::where('user_id', $userId)->where('is_archived', false)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'label' => $e->title . ($e->institution ? ' - ' . $e->institution : ''),
                    'datetime' => optional($e->target_completion_date)->toIso8601String(),
                ])->all(),

            'network' => NetworkContact::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'label' => $n->name . ($n->company ? ' - ' . $n->company : ''),
                    'datetime' => optional($n->next_follow_up_date)->toIso8601String(),
                ])->all(),

            'relationship' => PersonalRelationship::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'label' => $r->name . ($r->relation_label ? ' - ' . $r->relation_label : ''),
                    'datetime' => optional($r->next_planned_interaction)->toIso8601String(),
                ])->all(),

            'spiritual' => SpiritualPractice::where('user_id', $userId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($sp) => [
                    'id' => $sp->id,
                    'label' => ($sp->title ?: ucfirst(str_replace('_', ' ', $sp->practice_type))),
                    'datetime' => optional($sp->next_planned_date ?: $sp->practiced_at)->toIso8601String(),
                ])->all(),

            default => [],
        };
    }

    /**
     * True if any two of the given ISO datetime strings (plus the
     * reminder's own next_run_at) fall within $windowMinutes of each
     * other - a rough "these are basically happening at the same time"
     * check, not a true calendar-style overlap (most of these source
     * records are single points in time, not spans with a duration).
     */
    public function hasOverlap(array $datetimes, int $windowMinutes = 30): bool
    {
        $timestamps = collect($datetimes)
            ->filter()
            ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->timestamp)
            ->sort()
            ->values();

        for ($i = 1; $i < $timestamps->count(); $i++) {
            if (($timestamps[$i] - $timestamps[$i - 1]) < $windowMinutes * 60) {
                return true;
            }
        }

        return false;
    }
}
