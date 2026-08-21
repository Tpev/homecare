<?php

use App\Http\Controllers\ContentMcpOAuthAuthorizationController;
use App\Http\Controllers\ContentMcpOAuthClientController;
use App\Http\Controllers\ContentMcpOAuthMetadataController;
use App\Http\Controllers\ContentMcpOAuthTokenController;
use App\Http\Middleware\LimitContentMcpOAuthRequestSize;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function (): void {
    Route::get('/.well-known/oauth-authorization-server', [ContentMcpOAuthMetadataController::class, 'authorizationServer'])
        ->name('content-mcp.oauth.metadata');
    Route::get('/.well-known/oauth-protected-resource/mcp/content', [ContentMcpOAuthMetadataController::class, 'protectedResource'])
        ->name('content-mcp.oauth.resource');
    Route::get('/.well-known/oauth-protected-resource', [ContentMcpOAuthMetadataController::class, 'protectedResource'])
        ->name('content-mcp.oauth.resource-root');

    Route::post('/oauth/register', [ContentMcpOAuthClientController::class, 'store'])
        ->middleware(['throttle:content-mcp-registration', LimitContentMcpOAuthRequestSize::class])->name('content-mcp.oauth.register');
    Route::post('/oauth/token', [ContentMcpOAuthTokenController::class, 'issue'])
        ->middleware(['throttle:content-mcp-token', LimitContentMcpOAuthRequestSize::class])->name('content-mcp.oauth.token');
    Route::post('/oauth/revoke', [ContentMcpOAuthTokenController::class, 'revoke'])
        ->middleware(['throttle:content-mcp-token', LimitContentMcpOAuthRequestSize::class])->name('content-mcp.oauth.revoke');
    Route::post('/oauth/introspect', [ContentMcpOAuthTokenController::class, 'introspect'])
        ->middleware(['throttle:content-mcp-introspection', LimitContentMcpOAuthRequestSize::class])->name('content-mcp.oauth.introspect');
});

Route::middleware(['web', 'auth', 'verified', 'content.access', 'throttle:content-mcp-authorization'])
    ->group(function (): void {
        Route::get('/oauth/authorize', [ContentMcpOAuthAuthorizationController::class, 'show'])
            ->name('content-mcp.oauth.authorize');
        Route::post('/oauth/authorize', [ContentMcpOAuthAuthorizationController::class, 'decide'])
            ->name('content-mcp.oauth.decide');
    });
