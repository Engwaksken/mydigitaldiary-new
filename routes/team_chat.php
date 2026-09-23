<?php

use App\Http\Controllers\TeamChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'subscribed'])
    ->prefix('team-chat')
    ->name('team-chat.')
    ->group(function (): void {
        Route::get('/', [TeamChatController::class, 'index'])
            ->name('index');

        Route::post('/conversations', [TeamChatController::class, 'storeConversation'])
            ->name('conversations.store');

        Route::get('/conversations/{conversation}', [TeamChatController::class, 'show'])
            ->name('show');

        Route::post('/conversations/{conversation}/messages', [TeamChatController::class, 'storeMessage'])
            ->name('messages.store');

        Route::post('/conversations/{conversation}/read', [TeamChatController::class, 'markRead'])
            ->name('read');

        Route::post('/conversations/{conversation}/archive', [TeamChatController::class, 'archiveConversation'])
            ->name('archive');

        Route::put('/messages/{message}', [TeamChatController::class, 'updateMessage'])
            ->name('messages.update');

        Route::delete('/messages/{message}', [TeamChatController::class, 'destroyMessage'])
            ->name('messages.destroy');

        Route::post('/messages/{message}/reaction', [TeamChatController::class, 'react'])
            ->name('messages.react');

        Route::get('/attachments/{attachment}', [TeamChatController::class, 'downloadAttachment'])
            ->name('attachments.download');
    });
