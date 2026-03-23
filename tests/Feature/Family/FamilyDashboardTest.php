<?php

namespace Tests\Feature\Family;

use App\Support\MarketplaceEvent;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class FamilyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_user_sees_family_dashboard_sections(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($family)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Family Dashboard');
        $response->assertSee('How it works');
        $response->assertSee('Ready to review');
        $response->assertSee('Waiting applicants');
        $response->assertSee('Get started');
    }

    public function test_caregiver_user_sees_caregiver_dashboard_sections(): void
    {
        $caregiver = $this->createReadyCaregiver();

        $response = $this->actingAs($caregiver)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Caregiver Dashboard');
        $response->assertSee('Focus on the next best move.');
        $response->assertSee('Work inbox');
        $response->assertSee('Open full inbox');
    }

    public function test_family_dashboard_shows_top_unread_updates_digest(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $this->createNotification($family, MarketplaceEvent::NEW_APPLICANT, 'New caregiver application');
        $this->createNotification($family, MarketplaceEvent::HIRE_CONFIRMED, 'Hire confirmed');
        $this->createNotification($family, MarketplaceEvent::MESSAGE_RECEIVED, 'New message from caregiver');

        $response = $this->actingAs($family)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Top unread updates');
        $response->assertSee('New caregiver application');
        $response->assertSee('Hire confirmed');
    }

    public function test_caregiver_dashboard_shows_top_unread_updates_digest(): void
    {
        $caregiver = $this->createReadyCaregiver();

        $this->createNotification($caregiver, MarketplaceEvent::MATCHING_REQUEST_REMINDER, 'You have a new invitation');
        $this->createNotification($caregiver, MarketplaceEvent::APPLICATION_SUBMITTED, 'Application submitted');
        $this->createNotification($caregiver, MarketplaceEvent::CAREGIVER_HIRED, 'You were hired');

        $response = $this->actingAs($caregiver)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Top unread updates');
        $response->assertSee('You have a new invitation');
        $response->assertSee('Application submitted');
    }

    private function createReadyCaregiver(): User
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Reliable caregiver support. ', 6),
            'years_experience' => 3,
            'service_area_zip' => '27601',
            'service_radius_miles' => 15,
            'identity_verification_status' => 'approved',
            'identity_verified_at' => now(),
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
        ]);

        $skill = Skill::query()->create(['name' => 'Dashboard skill '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'Dashboard language '.$caregiver->id]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        return $caregiver;
    }

    private function createNotification(User $user, string $eventKey, string $title, bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\MarketplaceEventNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'event_key' => $eventKey,
                'title' => $title,
                'body' => 'Body for '.$title,
                'url' => route('dashboard'),
                'payload' => [],
            ],
            'read_at' => $read ? now()->subMinute() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
