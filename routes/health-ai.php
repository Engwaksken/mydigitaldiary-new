<?php

use App\Http\Controllers\HealthAiAdviceController;
use App\Http\Controllers\DailyFoodJournalController;
use App\Http\Controllers\HealthProfileController;
use App\Http\Controllers\WellbeingAiAdvisorController;
use App\Http\Controllers\WellbeingGoalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::put('/health-profile', [HealthProfileController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('health-profile.update');

    Route::post('/health-ai/sleep/refresh', [HealthAiAdviceController::class, 'sleep'])
        ->middleware('throttle:10,1')
        ->name('health-ai.sleep.refresh');


    Route::get('/daily-eating-history', [DailyFoodJournalController::class, 'index'])
        ->name('daily-food-history.index');

    Route::put('/daily-food-journal', [DailyFoodJournalController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('daily-food-journal.update');

    Route::post('/health-ai/diet/refresh', [HealthAiAdviceController::class, 'diet'])
        ->middleware('throttle:10,1')
        ->name('health-ai.diet.refresh');

    Route::post('/health-ai/wellbeing/advice', WellbeingAiAdvisorController::class)
        ->middleware('throttle:10,1')
        ->name('health-ai.wellbeing.advice');

    Route::get('/wellbeing-goals', function () {
        return redirect('/wellbeing?open_goals=1');
    })->name('wellbeing-goals.index');

    Route::put('/wellbeing-goals', [WellbeingGoalController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('wellbeing-goals.update');
});
