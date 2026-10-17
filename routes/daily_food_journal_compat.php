<?php

declare(strict_types=1);

use App\Http\Controllers\DailyFoodJournalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/daily-food-journal', function () {
        return redirect()->route('diet-logs.index', [
            'diet_tab' => 'today',
        ]);
    })->name('daily-food-journal.index');

    Route::put(
        '/daily-food-journal',
        [DailyFoodJournalController::class, 'update']
    )->name('daily-food-journal.update');
});
