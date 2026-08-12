<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin.email' => \App\Http\Middleware\EnsureAdminEmail::class,
            'content.access' => \App\Http\Middleware\EnsureContentAccess::class,
            'crm.access' => \App\Http\Middleware\EnsureCrmAccess::class,
            'sdr.access' => \App\Http\Middleware\EnsureSdrAccess::class,
            'caregiver.role' => \App\Http\Middleware\EnsureCaregiverRole::class,
            'family.role' => \App\Http\Middleware\EnsureFamilyRole::class,
            'continuous.coverage' => \App\Http\Middleware\EnsureContinuousCoverageAccess::class,
            'voice.agent' => \App\Http\Middleware\EnsureVoiceAgentToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
