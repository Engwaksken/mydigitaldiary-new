<?php

use App\Http\Controllers\GrowthStrategyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Growth & Referral — Mobile API
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'verified.api', 'mobile.idempotent'])
    ->prefix('growth')
    ->name('api.growth.')
    ->group(function (): void {
        Route::get('/dashboard', [GrowthStrategyController::class, 'dashboard'])
            ->name('dashboard');

        Route::post('/challenge/join', [GrowthStrategyController::class, 'joinChallenge'])
            ->name('challenge.join');

        Route::post('/referral', [GrowthStrategyController::class, 'referral'])
            ->name('referral');

        Route::get('/preferences', [GrowthStrategyController::class, 'preferences'])
            ->name('preferences');

        Route::put('/preferences', [GrowthStrategyController::class, 'updatePreferences'])
            ->name('preferences.update');

        Route::post('/track', [GrowthStrategyController::class, 'track'])
            ->name('track');
    });
