<?php

namespace Tests\Feature\Marketing;

use App\Models\PageViewEvent;
use App\Models\User;
use App\Services\Analytics\PageViewTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverLandingTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_landing_view_is_tracked_and_sets_anon_cookie(): void
    {
        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)')
            ->withHeader('Referer', 'https://google.com/')
            ->get('/caregivers?utm_source=google&utm_medium=cpc&utm_campaign=spring');

        $response->assertOk();

        $this->assertDatabaseCount('page_view_events', 1);
        $event = PageViewEvent::query()->firstOrFail();

        $this->assertSame(PageViewTracker::CAREGIVER_LANDING_EVENT, $event->event_name);
        $this->assertNull($event->user_id);
        $this->assertNotNull($event->anon_id);
        $this->assertSame('google', $event->utm_source);
        $this->assertSame('cpc', $event->utm_medium);
        $this->assertSame('spring', $event->utm_campaign);
        $this->assertNotNull($event->ip_hash);
        $this->assertNotNull($event->user_agent_hash);

        $cookieNames = collect($response->headers->getCookies())->map(fn ($cookie) => $cookie->getName());
        $this->assertTrue($cookieNames->contains(config('analytics.anon_cookie_name')));
    }

    public function test_authenticated_landing_view_is_tracked_by_user_id(): void
    {
        $user = User::factory()->create([
            'role' => 'caregiver',
            'email' => 'cg-tracking@test.local',
        ]);

        $response = $this
            ->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)')
            ->get('/caregivers');

        $response->assertOk();

        $this->assertDatabaseHas('page_view_events', [
            'event_name' => PageViewTracker::CAREGIVER_LANDING_EVENT,
            'user_id' => $user->id,
        ]);
    }

    public function test_bot_request_is_not_tracked(): void
    {
        $response = $this
            ->withHeader('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)')
            ->get('/caregivers');

        $response->assertOk();
        $this->assertDatabaseCount('page_view_events', 0);
    }
}

