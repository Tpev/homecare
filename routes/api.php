<?php

use App\Http\Controllers\Api\Content\V1\ContentController;
use App\Http\Controllers\Internal\VoiceAgentController;
use App\Models\ContentApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware('voice.agent')
    ->prefix('internal/voice')
    ->name('api.internal.voice.')
    ->group(function () {
        Route::get('/knowledge', [VoiceAgentController::class, 'knowledge'])->name('knowledge');
        Route::post('/leads', [VoiceAgentController::class, 'createLead'])->name('leads.create');
        Route::post('/callbacks', [VoiceAgentController::class, 'requestCallback'])->name('callbacks.create');
        Route::post('/reports', [VoiceAgentController::class, 'report'])->name('reports.create');
        Route::post('/provider-outreach-results', [VoiceAgentController::class, 'providerOutreachResult'])->name('provider-outreach-results.create');
        Route::post('/signup-link', [VoiceAgentController::class, 'signupLink'])->name('signup-link.create');
    });

Route::prefix('content/v1')
    ->name('api.content.v1.')
    ->middleware(['throttle:content-api', 'content.api.size', 'content.api', 'content.api.audit'])
    ->group(function (): void {
        Route::middleware('content.ability:'.ContentApiToken::ABILITY_READ)->group(function (): void {
            Route::get('/posts', [ContentController::class, 'index'])->name('posts.index');
            Route::get('/posts/{post}', [ContentController::class, 'show'])->name('posts.show');
            Route::get('/options', [ContentController::class, 'options'])->name('options.index');
            Route::get('/posts/{post}/preview', [ContentController::class, 'preview'])->name('posts.preview');
        });
        Route::middleware('content.ability:'.ContentApiToken::ABILITY_DRAFT)->group(function (): void {
            Route::post('/posts', [ContentController::class, 'store'])->name('posts.store');
            Route::patch('/posts/{post}', [ContentController::class, 'update'])->name('posts.update');
        });
        Route::post('/posts/{post}/media', [ContentController::class, 'uploadMedia'])
            ->middleware('content.ability:'.ContentApiToken::ABILITY_MEDIA)->name('posts.media');
        Route::post('/posts/{post}/audit', [ContentController::class, 'audit'])
            ->middleware('content.ability:'.ContentApiToken::ABILITY_AUDIT)->name('posts.audit');
        Route::post('/posts/{post}/submit', [ContentController::class, 'submit'])
            ->middleware('content.ability:'.ContentApiToken::ABILITY_SUBMIT)->name('posts.submit');
        Route::post('/posts/{post}/schedule', [ContentController::class, 'schedule'])
            ->middleware('content.ability:'.ContentApiToken::ABILITY_SCHEDULE)->name('posts.schedule');
        Route::post('/posts/{post}/publish', [ContentController::class, 'publish'])
            ->middleware('content.ability:'.ContentApiToken::ABILITY_PUBLISH)->name('posts.publish');
    });
