<?php

namespace App\Providers;

use App\Models\CareRequest;
use App\Models\CareRequestConversation;
use App\Policies\CareRequestConversationPolicy;
use App\Policies\CareRequestPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(CareRequest::class, CareRequestPolicy::class);
        Gate::policy(CareRequestConversation::class, CareRequestConversationPolicy::class);
    }
}
