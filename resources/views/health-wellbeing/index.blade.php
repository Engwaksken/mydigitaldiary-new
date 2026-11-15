@extends('layouts.app')

@section('title', 'Health & Wellbeing')

@section('content')
@php
    $daily = $summary['daily'] ?? [];
    $trend = $summary['trend'] ?? [];
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-teal-600">Connected wellbeing</p>
            <h1 class="text-2xl font-bold text-slate-900">Health & Wellbeing</h1>
            <p class="text-sm text-slate-500 mt-1">Diet, exercise, sleep, health records and your daily check-in in one place.</p>
        </div>
        <a href="{{ route('wellbeing.index') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-teal-600 text-white font-semibold">
            <i class="fa-solid fa-heart-pulse"></i> Daily check-in
        </a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        @foreach ([
            ['Sleep', $daily['sleep_hours'] !== null ? $daily['sleep_hours'].' hrs' : '-', 'fa-bed'],
            ['Exercise', ($daily['exercise_minutes'] ?? 0).' min', 'fa-person-running'],
            ['Meals', $daily['meals_logged'] ?? 0, 'fa-utensils'],
            ['Water', ($daily['water_percent'] ?? 0).'%', 'fa-droplet'],
            ['Wellbeing', $daily['wellbeing_score'] !== null ? $daily['wellbeing_score'].'/10' : '-', 'fa-heart-pulse'],
        ] as [$label,$value,$icon])
            <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
                <i class="fa-solid {{ $icon }} text-teal-600"></i>
                <div class="text-xs uppercase tracking-wide text-slate-500 mt-2">{{ $label }}</div>
                <div class="text-xl font-bold text-slate-900">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-5 gap-3">
        <a href="{{ route('diet-logs.index') }}" class="bg-white border rounded-xl p-4 hover:shadow-md"><i class="fa-solid fa-utensils text-orange-500"></i><div class="font-bold mt-2">Diet</div><div class="text-xs text-slate-500">Meals and food records</div></a>
        <a href="{{ route('exercise-logs.index') }}" class="bg-white border rounded-xl p-4 hover:shadow-md"><i class="fa-solid fa-person-running text-emerald-500"></i><div class="font-bold mt-2">Exercise</div><div class="text-xs text-slate-500">Sessions and minutes</div></a>
        <a href="{{ route('sleep-logs.index') }}" class="bg-white border rounded-xl p-4 hover:shadow-md"><i class="fa-solid fa-bed text-violet-500"></i><div class="font-bold mt-2">Sleep</div><div class="text-xs text-slate-500">Duration and quality</div></a>
        <a href="{{ route('health-checkups.index') }}" class="bg-white border rounded-xl p-4 hover:shadow-md"><i class="fa-solid fa-stethoscope text-rose-500"></i><div class="font-bold mt-2">Health</div><div class="text-xs text-slate-500">Checkups and measurements</div></a>
        <a href="{{ route('wellbeing.index') }}" class="bg-white border rounded-xl p-4 hover:shadow-md"><i class="fa-solid fa-heart-pulse text-teal-500"></i><div class="font-bold mt-2">Daily Wellbeing</div><div class="text-xs text-slate-500">Mood, energy, stress and self-care</div></a>
    </div>

    <div class="grid lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h2 class="font-bold text-slate-900">Today</h2>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-slate-500">Sleep quality</dt><dd class="font-semibold">{{ ucfirst($daily['sleep_quality'] ?? '-') }}</dd></div>
                <div><dt class="text-slate-500">Exercise sessions</dt><dd class="font-semibold">{{ $daily['exercise_sessions'] ?? 0 }}</dd></div>
                <div><dt class="text-slate-500">Energy</dt><dd class="font-semibold">{{ $daily['energy_level'] !== null ? $daily['energy_level'].'/5' : '-' }}</dd></div>
                <div><dt class="text-slate-500">Stress</dt><dd class="font-semibold">{{ $daily['stress_level'] !== null ? $daily['stress_level'].'/5' : '-' }}</dd></div>
                <div><dt class="text-slate-500">Mood</dt><dd class="font-semibold">{{ ucfirst($daily['mood'] ?? '-') }}</dd></div>
                <div><dt class="text-slate-500">Steps</dt><dd class="font-semibold">{{ number_format((int)($daily['steps'] ?? 0)) }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h2 class="font-bold text-slate-900">Recent trend · {{ $trend['days'] ?? 7 }} days</h2>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-slate-500">Avg sleep</dt><dd class="font-semibold">{{ $trend['avg_sleep_hours'] !== null ? $trend['avg_sleep_hours'].' hrs' : '-' }}</dd></div>
                <div><dt class="text-slate-500">Exercise days</dt><dd class="font-semibold">{{ $trend['exercise_days'] ?? 0 }}</dd></div>
                <div><dt class="text-slate-500">Avg exercise</dt><dd class="font-semibold">{{ $trend['avg_exercise_minutes'] !== null ? $trend['avg_exercise_minutes'].' min' : '-' }}</dd></div>
                <div><dt class="text-slate-500">Avg water target</dt><dd class="font-semibold">{{ $trend['avg_water_percent'] !== null ? $trend['avg_water_percent'].'%' : '-' }}</dd></div>
                <div><dt class="text-slate-500">Avg wellbeing</dt><dd class="font-semibold">{{ $trend['avg_wellbeing_score'] !== null ? $trend['avg_wellbeing_score'].'/10' : '-' }}</dd></div>
                <div><dt class="text-slate-500">Meals logged</dt><dd class="font-semibold">{{ $trend['meals_logged'] ?? 0 }}</dd></div>
            </dl>
        </div>
    </div>

    @if (!empty($summary['observations']))
        <div class="bg-teal-50 border border-teal-100 rounded-xl p-5">
            <h2 class="font-bold text-teal-900">From your records</h2>
            <ul class="mt-3 space-y-2 text-sm text-teal-900">
                @foreach ($summary['observations'] as $observation)
                    <li class="flex gap-2"><i class="fa-solid fa-circle-check mt-1"></i><span>{{ $observation }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="text-xs text-slate-500">{{ $summary['notice'] ?? '' }}</p>
</div>
@endsection
