<?php

use App\Http\Controllers\DailyFoodJournalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::put('/daily-food-history/{entry}', [DailyFoodJournalController::class, 'updateEntry'])
        ->whereNumber('entry')
        ->name('daily-food-history.update-entry');
});
