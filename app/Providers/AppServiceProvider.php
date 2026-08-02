<?php

namespace App\Providers;

use App\Contracts\AiCopilotResponder;
use App\Listeners\SendOpsUserRegisteredAlert;
use App\Models\CareRequest;
use App\Models\CareRequestConversation;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Observers\CareRequestObserver;
use App\Observers\SupportTicketObserver;
use App\Policies\CareRequestConversationPolicy;
use App\Policies\CareRequestPolicy;
use App\Policies\SupportTicketMessagePolicy;
use App\Policies\SupportTicketPolicy;
use App\Services\AiCopilot\OpenAiCopilotResponder;
use App\Services\AiCopilot\RuleBasedCopilotResponder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiCopilotResponder::class, function ($app) {
            if (! config('features.ai_request_copilot')) {
                return $app->make(RuleBasedCopilotResponder::class);
            }

            if ((string) config('services.openai.key') === '') {
                return $app->make(RuleBasedCopilotResponder::class);
            }

            return $app->make(OpenAiCopilotResponder::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(CareRequest::class, CareRequestPolicy::class);
        Gate::policy(CareRequestConversation::class, CareRequestConversationPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
        Gate::policy(SupportTicketMessage::class, SupportTicketMessagePolicy::class);
        CareRequest::observe(CareRequestObserver::class);
        SupportTicket::observe(SupportTicketObserver::class);

        Event::listen(Registered::class, SendOpsUserRegisteredAlert::class);
    }
}
