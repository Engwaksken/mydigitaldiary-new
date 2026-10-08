<?php

namespace App\Http\Controllers;

use App\Models\HealthCheckup;
use App\Services\PersonalHealthGoalProgressService;
use Illuminate\Http\Request;

class HealthCheckupController extends CrudController
{
    protected string $model = HealthCheckup::class;
    protected string $routeName = 'health-checkups';
    protected string $title = 'Health Checkup';
    protected string $icon = 'fa-solid fa-stethoscope';
    protected string $accent = 'pink';
    protected string $dateField = 'checkup_date';

    protected array $fields = [
        ['tab' => 'Checkup', 'name' => 'checkup_type', 'label' => 'Type (e.g. Dental, General, Eye)', 'type' => 'text', 'required' => true],
        ['tab' => 'Checkup', 'table' => 'meta', 'name' => 'checkup_date', 'label' => 'Checkup Date & Time', 'type' => 'datetime-local', 'required' => true],
        ['tab' => 'Checkup', 'name' => 'doctor_name', 'label' => 'Doctor / Clinic', 'type' => 'text', 'placeholder' => 'e.g. Clinic or health professional'],
        ['tab' => 'Measurements', 'name' => 'weight_kg', 'label' => 'Weight (kg, optional)', 'type' => 'number'],
        ['tab' => 'Measurements', 'name' => 'blood_pressure_systolic', 'label' => 'Blood Pressure – Systolic (optional)', 'type' => 'number'],
        ['tab' => 'Measurements', 'name' => 'blood_pressure_diastolic', 'label' => 'Blood Pressure – Diastolic (optional)', 'type' => 'number'],
        ['tab' => 'Measurements', 'name' => 'heart_rate_bpm', 'label' => 'Heart Rate (bpm, optional)', 'type' => 'number'],
        ['tab' => 'Checkup', 'table' => 'date', 'name' => 'next_due_date', 'label' => 'Next Checkup Due (Date & Time)', 'type' => 'datetime-local'],
        ['tab' => 'Findings', 'name' => 'findings', 'label' => 'Findings / Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'checkup_type' => 'required|string|max:255',
        'checkup_date' => 'required|date',
        'doctor_name' => 'nullable|string|max:255',
        'weight_kg' => 'nullable|numeric|min:1|max:500',
        'blood_pressure_systolic' => 'nullable|integer|min:40|max:300',
        'blood_pressure_diastolic' => 'nullable|integer|min:20|max:200',
        'heart_rate_bpm' => 'nullable|integer|min:20|max:250',
        'next_due_date' => 'nullable|date',
        'findings' => 'nullable|string',
    ];

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = HealthCheckup::where('user_id', $userId);

        return [
            ['label' => 'Upcoming', 'value' => (string) (clone $base)->whereNotNull('next_due_date')->where('next_due_date', '>=', now())->count(), 'icon' => 'fa-solid fa-calendar-days', 'color' => 'blue'],
            ['label' => 'Overdue', 'value' => (string) (clone $base)->whereNotNull('next_due_date')->where('next_due_date', '<', now())->count(), 'icon' => 'fa-solid fa-triangle-exclamation', 'color' => 'rose'],
            ['label' => 'Total records', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-stethoscope', 'color' => 'pink'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $counts = HealthCheckup::where('user_id', $userId)
            ->selectRaw('checkup_type, COUNT(*) as total')->groupBy('checkup_type')->orderByDesc('total')->limit(8)->pluck('total', 'checkup_type');

        if ($counts->isEmpty()) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'title' => 'Checkups by Type',
            'labels' => $counts->keys()->all(),
            'datasets' => [['data' => $counts->values()->all()]],
        ];
    }

    protected function afterSave(Request $request, $item, bool $wasCreated): void
    {
        app(PersonalHealthGoalProgressService::class)->sync($request->user());
    }

    public function destroy(Request $request, int $id)
    {
        $item = HealthCheckup::where('user_id',$request->user()->id)->findOrFail($id);
        $item->delete();
        app(PersonalHealthGoalProgressService::class)->sync($request->user());

        return back()->with('success','Health Checkup deleted.');
    }

}
