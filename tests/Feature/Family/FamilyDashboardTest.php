<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\RequestsIndex;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
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
        $response->assertSee('Right now');
        $response->assertSee('Billing is not ready yet.');
        $response->assertSee('Next visit');
        $response->assertSee('Book again');
        $response->assertDontSee('Care overview');
        $response->assertDontSee('Your care');
        $response->assertDontSee('Needs attention');
        $response->assertDontSee('Needs your attention');
        $response->assertDontSee('Also needs attention');
        $response->assertDontSee('What to do now');
    }

    public function test_caregiver_user_sees_caregiver_dashboard_sections(): void
    {
        $caregiver = $this->createReadyCaregiver();

        $response = $this->actingAs($caregiver)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Caregiver Dashboard');
        $response->assertSee("You're ready to start getting booked.", false);
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
        $response->assertSee('New updates');
        $response->assertSee('New caregiver application');
    }

    public function test_family_dashboard_next_visit_shows_who_is_coming(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
            'stripe_customer_id' => 'cus_dashboard_next_visit',
        ]);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Caroline Petrini-Poli',
        ]);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Afternoon companionship visit',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->addDay()->setTime(14, 0),
            'requested_end_at' => now()->addDay()->setTime(16, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '1520 Home Creek Drive',
        ]);

        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay()->setTime(14, 0),
            'scheduled_end_at' => now()->addDay()->setTime(16, 0),
        ]);
        CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_AUTHORIZED,
            'currency' => 'usd',
            'amount_authorized_cents' => 7200,
            'authorization_expires_at' => now()->addDays(5),
        ]);

        $response = $this->actingAs($family)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Next visit');
        $response->assertSee('Right now');
        $response->assertSee('You have 1 upcoming visit.');
        $response->assertSee('Caroline Petrini-Poli is coming');
        $response->assertSee('Payment confirmed. No action needed.');
        $response->assertSee('Coming');
        $response->assertSee('Caroline Petrini-Poli');
        $response->assertDontSee('Needs your attention');
        $response->assertDontSee('Also needs attention');
        $response->assertDontSee('What to do now');
    }

    public function test_failed_visit_payment_is_a_consistent_action_on_home_and_care(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
            'stripe_customer_id' => 'cus_dashboard_failed_payment',
        ]);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Charles Petrini-Poli',
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning support visit',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->addDay()->setTime(7, 30),
            'requested_end_at' => now()->addDay()->setTime(9, 30),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '100 Main Street',
        ]);

        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay()->setTime(7, 30),
            'scheduled_end_at' => now()->addDay()->setTime(9, 30),
        ]);
        CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_FAILED,
            'currency' => 'usd',
            'last_error' => 'Your card was declined.',
            'failed_at' => now(),
        ]);

        $visitUrl = route('family.requests.show', $request->id);

        $this->actingAs($family)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Payment needs attention.')
            ->assertSee('Your card was declined.')
            ->assertSee('Fix payment')
            ->assertSee($visitUrl, false);

        Livewire::actingAs($family)
            ->test(RequestsIndex::class)
            ->assertViewHas('attentionCount', 1)
            ->assertSee('Payment needs attention')
            ->assertSee('Fix payment')
            ->assertDontSee('Nothing urgent right now')
            ->set('status', CareRequest::STATUS_OPEN)
            ->assertViewHas('attentionCount', 1)
            ->assertSee('Payment needs attention')
            ->assertSee('Fix payment');
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

    public function test_family_dashboard_uses_the_true_next_booking_with_more_than_twenty_five_visits(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
            'stripe_customer_id' => 'cus_many_dashboard_visits',
        ]);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Earliest Visit Caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active']);

        for ($day = 1; $day <= 30; $day++) {
            $request = CareRequest::query()->create([
                'family_user_id' => $family->id,
                'title' => $day === 1 ? 'True earliest recurring visit' : 'Later recurring visit '.$day,
                'request_type' => CareRequest::TYPE_ONE_TIME,
                'status' => CareRequest::STATUS_FILLED,
                'requested_start_at' => now()->addDays($day)->setTime(9, 0),
                'requested_end_at' => now()->addDays($day)->setTime(11, 0),
                'address_line1' => '1 Schedule Lane',
                'city' => 'Raleigh',
                'state' => 'NC',
                'zip' => '27601',
            ]);
            CareBooking::query()->create([
                'care_request_id' => $request->id,
                'family_user_id' => $family->id,
                'caregiver_user_id' => $caregiver->id,
                'status' => CareBooking::STATUS_SCHEDULED,
                'scheduled_start_at' => $request->requested_start_at,
                'scheduled_end_at' => $request->requested_end_at,
            ]);
        }

        $this->actingAs($family)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('True earliest recurring visit');
        $this->actingAs($caregiver)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('True earliest recurring visit');
        $this->actingAs($caregiver)
            ->get(route('caregiver.shifts.index'))
            ->assertOk()
            ->assertSee('True earliest recurring visit');
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
