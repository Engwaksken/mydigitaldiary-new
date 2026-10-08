<?php

namespace App\Http\Controllers;

use App\Models\Project;

class ProjectController extends CrudController
{
    protected string $model = Project::class;
    protected string $routeName = 'projects';
    protected string $title = 'Project';
    protected string $icon = 'fa-solid fa-diagram-project';
    protected string $accent = 'blue';

    protected array $fields = [
        ['name' => 'name', 'label' => 'Project Name', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Kitchen Renovation'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => [
            'planned' => 'Planned', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed',
        ]],
        ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date'],
        ['name' => 'deadline', 'label' => 'Deadline', 'type' => 'date', 'table' => 'date'],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'name' => 'required|string|max:255',
        'status' => 'required|in:planned,in_progress,on_hold,completed',
        'start_date' => 'nullable|date',
        'deadline' => 'nullable|date',
        'description' => 'nullable|string',
    ];

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = Project::where('user_id', $userId);

        return [
            ['label' => 'Active', 'value' => (string) (clone $base)->whereIn('status', ['planned', 'in_progress'])->count(), 'icon' => 'fa-solid fa-diagram-project', 'color' => 'blue'],
            ['label' => 'Completed', 'value' => (string) (clone $base)->where('status', 'completed')->count(), 'icon' => 'fa-solid fa-circle-check', 'color' => 'emerald'],
            ['label' => 'Total', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-layer-group', 'color' => 'slate'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $counts = Project::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($counts->isEmpty()) {
            return null;
        }

        $labels = ['planned', 'in_progress', 'on_hold', 'completed'];

        return [
            'type' => 'doughnut',
            'title' => 'Projects by Status',
            'labels' => array_map(fn ($l) => ucwords(str_replace('_', ' ', $l)), $labels),
            'datasets' => [['data' => array_map(fn ($l) => (int) ($counts[$l] ?? 0), $labels)]],
        ];
    }
}
