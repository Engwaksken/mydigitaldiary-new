<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ProjectTaskReminderService
{
    public const OFFSETS = [0,5,15,30,60,120,1440];

    /**
     * Create or refresh the reminder set for a project task. Like the daily
     * planner, a task is reminded at least three times (the day before, a
     * couple of hours before, and at the due time) so it cannot be forgotten.
     */
    public function sync(ProjectTask $task, User $user): ?Reminder
    {
        if (! Schema::hasTable('reminders')) return null;

        if (! $task->reminder_enabled || $task->status === 'done') {
            $this->cancel($task, $user);
            return null;
        }

        $at = $this->remindAt($task, $user);
        if (! $at) {
            $this->cancel($task, $user);
            return null;
        }

        $nudges = app(ReminderCadenceService::class)->nudgeTimes($at);
        $columns = Schema::getColumnListing('reminders');

        $primary = null;
        $activeSignatures = [];

        foreach ($nudges as $index => $nudgeAt) {
            $signature = ReminderCadenceService::signature($task->id, $index);
            $activeSignatures[] = $signature;

            $lookup = ['user_id' => $user->id];
            if (in_array('source_type', $columns, true) && in_array('source_id', $columns, true)) {
                $lookup['source_type'] = 'project_task';
                $lookup['source_id'] = $task->id;

                if (in_array('source_signature', $columns, true)) {
                    $lookup['source_signature'] = $signature;
                }
            } elseif ($task->reminder_id) {
                $lookup['id'] = $task->reminder_id;
            }

            $values = [];
            $this->put($values, $columns, 'title', 'Project Task: '.$task->title);
            $this->put($values, $columns, 'message', 'Reminder for project task "'.$task->title.'".');
            $this->put($values, $columns, 'description', 'Reminder for project task "'.$task->title.'".');
            $this->put($values, $columns, 'frequency', 'once');
            $this->put($values, $columns, 'next_run_at', $nudgeAt);
            $this->put($values, $columns, 'remind_at', $nudgeAt);
            $this->put($values, $columns, 'reminder_at', $nudgeAt);
            $this->put($values, $columns, 'channel', $task->reminder_channel ?: 'push');
            $this->put($values, $columns, 'is_active', true);
            $this->put($values, $columns, 'alarm_enabled', true);
            $this->put($values, $columns, 'status', 'active');
            $this->put($values, $columns, 'module', 'project-tasks');
            $this->put($values, $columns, 'source_type', 'project_task');
            $this->put($values, $columns, 'source_id', $task->id);
            $this->put($values, $columns, 'source_signature', $signature);

            $reminder = Reminder::query()->updateOrCreate($lookup, $values);

            // Primary points at the closest-to-due nudge (the last in OFFSETS order).
            $primary = $reminder;
        }

        $this->cancelStaleNudges($task, $user, $activeSignatures, $columns);

        if (Schema::hasColumn('project_tasks', 'reminder_id') && $primary && (int) $task->reminder_id !== (int) $primary->id) {
            $task->forceFill(['reminder_id' => $primary->id])->saveQuietly();
        }

        return $primary;
    }

    public function cancel(ProjectTask $task, User $user): void
    {
        if (! Schema::hasTable('reminders')) return;
        $columns = Schema::getColumnListing('reminders');
        $query = Reminder::query()->where('user_id', $user->id);
        if (in_array('source_type', $columns, true) && in_array('source_id', $columns, true)) {
            $query->where('source_type', 'project_task')->where('source_id', $task->id);
        } elseif ($task->reminder_id) {
            $query->whereKey($task->reminder_id);
        } else return;

        if (in_array('is_active', $columns, true)) $query->update(['is_active' => false]);
        elseif (in_array('status', $columns, true)) $query->update(['status' => 'cancelled']);
        else $query->delete();
    }

    private function cancelStaleNudges(ProjectTask $task, User $user, array $activeSignatures, array $columns): void
    {
        if (! in_array('source_signature', $columns, true)) {
            return;
        }

        Reminder::query()
            ->where('user_id', $user->id)
            ->where('source_type', 'project_task')
            ->where('source_id', $task->id)
            ->whereNotIn('source_signature', $activeSignatures)
            ->update(['is_active' => false]);
    }

    private function remindAt(ProjectTask $task, User $user): ?Carbon
    {
        $tz = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        if ($task->reminder_custom_at) return Carbon::parse($task->reminder_custom_at, $tz);
        if (! $task->due_date || ! $task->due_time) return null;
        $due = Carbon::parse($task->due_date->format('Y-m-d').' '.substr((string) $task->due_time, 0, 8), $tz);
        return $due->subMinutes(max(0, (int) ($task->reminder_offset_minutes ?? 0)));
    }

    private function put(array &$values, array $columns, string $key, mixed $value): void
    {
        if (in_array($key, $columns, true)) $values[$key] = $value;
    }
}
