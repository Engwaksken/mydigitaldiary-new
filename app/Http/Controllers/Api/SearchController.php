<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\EducationPlan;
use App\Models\Expense;
use App\Models\Income;
use App\Models\NetworkContact;
use App\Models\PersonalRelationship;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Searches the highest-value, most commonly-used modules rather than
 * literally every one of the app's 20+ tables - Plans, Expenses,
 * Income, Budgets, Projects, Project Tasks, Reminders, Education
 * Plans, Network Contacts, and Personal Relationships. Each result's
 * "module" field matches a mobile ModuleConfig endpoint string
 * exactly, so the app can navigate straight to the right screen from
 * a tap.
 */
class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $userId = $request->user()->id;
        $results = collect();

        Plan::where('user_id', $userId)->where('is_archived', false)->where('title', 'like', "%{$query}%")
            ->limit(10)->get()->each(fn ($r) => $results->push([
                'module' => 'plans', 'id' => $r->id, 'title' => $r->title, 'subtitle' => ucfirst($r->status ?? ''),
            ]));

        Expense::where('user_id', $userId)->where('is_archived', false)->where('category', 'like', "%{$query}%")
            ->orWhere(fn ($q) => $q->where('user_id', $userId)->where('notes', 'like', "%{$query}%"))
            ->limit(10)->get()->each(fn ($r) => $results->push([
                'module' => 'expenses', 'id' => $r->id, 'title' => $r->category, 'subtitle' => $r->notes,
            ]));

        Income::where('user_id', $userId)->where('is_archived', false)->where('source', 'like', "%{$query}%")
            ->limit(10)->get()->each(fn ($r) => $results->push([
                'module' => 'incomes', 'id' => $r->id, 'title' => $r->source, 'subtitle' => $r->category,
            ]));

        Budget::where('user_id', $userId)->where('is_archived', false)->where('category', 'like', "%{$query}%")
            ->limit(10)->get()->each(fn ($r) => $results->push([
                'module' => 'budgets', 'id' => $r->id, 'title' => $r->category, 'subtitle' => $r->period,
            ]));

        Project::where('user_id', $userId)->where('is_archived', false)->where('name', 'like', "%{$query}%")
            ->limit(10)->get()->each(fn ($r) => $results->push([
                'module' => 'projects', 'id' => $r->id, 'title' => $r->name, 'subtitle' => ucfirst($r->status ?? ''),
            ]));

        ProjectTask::where('user_id', $userId)->where('is_archived', false)->where('title', 'like', "%{$query}%")
            ->limit(10)->get()->each(fn ($r) => $results->push([
                'module' => 'project-tasks', 'id' => $r->id, 'title' => $r->title, 'subtitle' => ucfirst($r->status ?? ''),
            ]));

        Reminder::where('user_id', $userId)->where('is_archived', false)->where('title', 'like', "%{$query}%")
            ->limit(10)->get()->each(fn ($r) => $results->push([
                'module' => 'reminders', 'id' => $r->id, 'title' => $r->title, 'subtitle' => $r->frequency,
            ]));

        EducationPlan::where('user_id', $userId)->where('is_archived', false)->where('title', 'like', "%{$query}%")
            ->limit(10)->get()->each(fn ($r) => $results->push([
                'module' => 'education-plans', 'id' => $r->id, 'title' => $r->title, 'subtitle' => $r->institution,
            ]));

        NetworkContact::where('user_id', $userId)->where('is_archived', false)->where('name', 'like', "%{$query}%")
            ->limit(10)->get()->each(fn ($r) => $results->push([
                'module' => 'network-contacts', 'id' => $r->id, 'title' => $r->name, 'subtitle' => $r->company,
            ]));

        PersonalRelationship::where('user_id', $userId)->where('is_archived', false)->where('name', 'like', "%{$query}%")
            ->limit(10)->get()->each(fn ($r) => $results->push([
                'module' => 'relationships', 'id' => $r->id, 'title' => $r->name, 'subtitle' => $r->relation_label,
            ]));

        return response()->json(['data' => $results->take(40)->values()]);
    }
}
