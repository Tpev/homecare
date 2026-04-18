<?php

use App\Http\Controllers\Internal\VoiceAgentController;
use Illuminate\Support\Facades\Route;

Route::middleware('voice.agent')
    ->prefix('internal/voice')
    ->name('api.internal.voice.')
    ->group(function () {
        Route::get('/knowledge', [VoiceAgentController::class, 'knowledge'])->name('knowledge');
        Route::post('/leads', [VoiceAgentController::class, 'createLead'])->name('leads.create');
        Route::post('/callbacks', [VoiceAgentController::class, 'requestCallback'])->name('callbacks.create');
        Route::post('/reports', [VoiceAgentController::class, 'report'])->name('reports.create');
        Route::post('/signup-link', [VoiceAgentController::class, 'signupLink'])->name('signup-link.create');
    });
