<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class ContentMcpServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('content-mcp-registration', fn (Request $request): Limit => Limit::perHour(
            (int) config('content_mcp.registration_rate_limit_per_hour')
        )->by('content-mcp-registration:'.hash('sha256', (string) $request->ip())));

        RateLimiter::for('content-mcp-token', fn (Request $request): Limit => Limit::perMinute(
            (int) config('content_mcp.token_rate_limit_per_minute')
        )->by('content-mcp-token:'.hash('sha256', (string) $request->ip())));

        RateLimiter::for('content-mcp-authorization', fn (Request $request): Limit => Limit::perMinute(
            (int) config('content_mcp.authorization_rate_limit_per_minute')
        )->by('content-mcp-authorization:'.hash('sha256', (string) $request->user()?->id.':'.(string) $request->ip())));

        RateLimiter::for('content-mcp-introspection', fn (Request $request): Limit => Limit::perMinute(
            (int) config('content_mcp.introspection_rate_limit_per_minute')
        )->by('content-mcp-introspection:'.hash('sha256', (string) $request->ip())));

        $this->loadRoutesFrom(base_path('routes/content-mcp.php'));
    }
}
