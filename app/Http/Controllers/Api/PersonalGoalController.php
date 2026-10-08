<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ApiCrudIndexRequest;
use App\Models\PersonalGoal;
use App\Services\PersonalHealthGoalProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalGoalController extends ApiCrudController
{
    protected string $model = PersonalGoal::class;

    protected array $rules = [
        'module' => 'required|in:finance,savings,education,spiritual,health,exercise,diet,productivity,projects,personal',
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'start_date' => 'nullable|date',
        'target_date' => 'nullable|date|after_or_equal:start_date',
        'target_value' => 'nullable|numeric|min:0',
        'current_value' => 'nullable|numeric|min:0',
        'progress_percent' => 'required|integer|min:0|max:100',
        'status' => 'required|in:not_started,in_progress,completed,paused',
        'priority' => 'required|in:low,medium,high',
        'reminder_at' => 'nullable|date',
        'notes' => 'nullable|string',
    ];

    /**
     * List the authenticated user's personal goals.
     *
     * Health-linked goals are synchronised before returning the list so
     * current_value and progress_percent remain aligned with health data.
     *
     * Must keep the parent's ApiCrudIndexRequest type: a plain Request
     * here is passed straight to parent::index() and throws a TypeError
     * (every goals list request answered 500 "Server Error").
     */
    public function index(ApiCrudIndexRequest $request): JsonResponse
    {
        try {
            app(PersonalHealthGoalProgressService::class)
                ->sync($request->user());
        } catch (\Throwable $e) {
            report($e);
        }

        return parent::index($request);
    }

    /**
     * Create a personal goal.
     *
     * Health goals derive their current value and progress from the
     * health-progress service rather than accepting manual progress values.
     */
    public function store(Request $request): JsonResponse
    {
        $isHealthGoal =
            $request->input('module') === 'health';

        if ($isHealthGoal) {
            $request->merge([
                'current_value' => 0,
                'progress_percent' => 0,
            ]);
        }

        $response =
            parent::store($request);

        if ($isHealthGoal) {
            try {
                app(PersonalHealthGoalProgressService::class)
                    ->sync($request->user());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $response;
    }

    /**
     * Update an existing personal goal.
     *
     * For health goals, automatically calculated progress is preserved and
     * re-synchronised after the goal is updated.
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $existing =
            PersonalGoal::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->findOrFail($id);

        $module =
            $request->input(
                'module',
                $existing->module
            );

        $isHealthGoal =
            $module === 'health';

        if ($isHealthGoal) {
            $request->merge([
                'current_value' =>
                    $existing->current_value
                    ?? 0,

                'progress_percent' =>
                    $existing->progress_percent
                    ?? 0,
            ]);
        }

        $response =
            parent::update(
                $request,
                $id
            );

        if ($isHealthGoal) {
            try {
                app(PersonalHealthGoalProgressService::class)
                    ->sync($request->user());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $response;
    }

    /**
     * Apply optional module filtering to the goal listing.
     */
    protected function filteredIndexQuery(
        Request $request
    ): Builder {
        $query =
            parent::filteredIndexQuery(
                $request
            );

        if ($request->filled('module')) {
            $query->where(
                'module',
                $request->query('module')
            );
        }

        return $query;
    }
}