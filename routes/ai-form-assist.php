<?php

declare(strict_types=1);

use App\Http\Controllers\AiFormAssistController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post(
        '/ai/form-assist',
        [AiFormAssistController::class, 'generate']
    )->middleware('throttle:15,1')->name('ai.form-assist');
});
