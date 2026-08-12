<?php

namespace App\Providers;

use App\Contracts\AiCopilotResponder;
use App\Listeners\AttributeContentRegistration;
use App\Listeners\SendOpsUserRegisteredAlert;
use App\Models\BlogPost;
use App\Models\CareRecipientProfile;
use App\Models\CareRecipientProfileVersion;
use App\Models\CareRequest;
use App\Models\CareRequestConversation;
use App\Models\MediaAsset;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Observers\CareRequestObserver;
use App\Observers\SupportTicketAuditObserver;
use App\Observers\SupportTicketObserver;
use App\Policies\BlogPostPolicy;
use App\Policies\CareRecipientProfilePolicy;
use App\Policies\CareRecipientProfileVersionPolicy;
use App\Policies\CareRequestConversationPolicy;
use App\Policies\CareRequestPolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\SupportTicketMessagePolicy;
use App\Policies\SupportTicketPolicy;
use App\Services\AiCopilot\OpenAiCopilotResponder;
use App\Services\AiCopilot\RuleBasedCopilotResponder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('content-api', function (Request $request): Limit {
            $identity = $request->bearerToken()
                ? 'token:'.$request->bearerToken()
                : 'ip:'.(string) $request->ip();

            return Limit::perMinute((int) config('content_api.rate_limit_per_minute'))
                ->by('content-api:'.hash('sha256', $identity))
                ->response(fn (Request $request, array $headers) => response()->json([
                    'message' => 'The content API rate limit has been exceeded.',
                    'code' => 'rate_limited',
                    'errors' => [],
                    'meta' => ['retry_after_seconds' => (int) ($headers['Retry-After'] ?? 60)],
                ], 429, $headers));
        });

        Gate::policy(CareRequest::class, CareRequestPolicy::class);
        Gate::policy(BlogPost::class, BlogPostPolicy::class);
        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);
        Gate::policy(CareRecipientProfile::class, CareRecipientProfilePolicy::class);
        Gate::policy(CareRecipientProfileVersion::class, CareRecipientProfileVersionPolicy::class);
        Gate::policy(CareRequestConversation::class, CareRequestConversationPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
        Gate::policy(SupportTicketMessage::class, SupportTicketMessagePolicy::class);
        CareRequest::observe(CareRequestObserver::class);
        SupportTicket::observe(SupportTicketAuditObserver::class);
        SupportTicket::observe(SupportTicketObserver::class);

        Event::listen(Registered::class, SendOpsUserRegisteredAlert::class);
        Event::listen(Registered::class, AttributeContentRegistration::class);
    }
}
