<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CareRequestShow;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCareRequestScheduleDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_separates_confirmed_and_requested_times_before_outreach_and_keeps_overrides_collapsed(): void
    {
        config(['app.timezone' => 'America/New_York']);
        $request = $this->createRequest([
            'requested_start_at' => '2035-01-10 09:30:00',
            'requested_end_at' => '2035-01-10 12:00:00',
        ]);
        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $request->family_user_id,
            'caregiver_user_id' => User::factory()->create(['role' => 'caregiver'])->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => '2035-01-10 11:00:00',
            'scheduled_end_at' => '2035-01-10 15:00:00',
        ]);

        $component = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(CareRequestShow::class, ['careRequest' => $request])
            ->assertSeeInOrder(['Confirmed booking', '11:00 AM', '4 hours', 'Requested care', '9:30 AM', '2 hours 30 minutes', 'Request details', 'Lolo caregiver outreach', 'Admin controls'])
            ->assertSee('America/New_York')
            ->assertSee('EST (UTC-05:00)')
            ->assertSee('Posted')
            ->assertDontSee('Shift window');

        $document = new DOMDocument;
        @$document->loadHTML($component->html());
        $xpath = new DOMXPath($document);
        $controls = $xpath->query('//details[summary[contains(., "Admin controls")]]');
        $this->assertCount(1, $controls);
        $this->assertFalse($controls->item(0)->hasAttribute('open'));
        $this->assertGreaterThan(0, $xpath->query('.//button[contains(., "Delete request")]', $controls->item(0))->length);
        $this->assertGreaterThan(0, $xpath->query('.//button[contains(., "Cancelled")]', $controls->item(0))->length);

        $component->call('forceRequestStatus', CareRequest::STATUS_FILLED)->assertHasNoErrors();
        $this->assertSame(CareRequest::STATUS_FILLED, $request->fresh()->status);
    }

    public function test_detail_shows_each_recurring_visit_duration_weekly_total_and_date_range(): void
    {
        config(['app.timezone' => 'America/New_York']);
        $request = $this->createRequest([
            'request_type' => CareRequest::TYPE_RECURRING,
            'recurring_schedule' => [
                ['day' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
                ['day' => 3, 'start_time' => '13:30', 'end_time' => '15:00'],
            ],
            'recurring_starts_on' => '2035-01-08',
            'recurring_ends_on' => null,
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(CareRequestShow::class, ['careRequest' => $request])
            ->assertSee('Requested weekly care')
            ->assertSee('2 visits · 4 hours 30 minutes/week')
            ->assertSee('No end date specified')
            ->assertSee('Monday')
            ->assertSee('Wednesday')
            ->assertSee('3 hours')
            ->assertSee('1 hour 30 minutes')
            ->assertSee('America/New_York')
            ->assertSee('Next requested visit:');
    }

    public function test_detail_identifies_missing_care_schedule_without_using_posted_date(): void
    {
        $request = $this->createRequest();

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(CareRequestShow::class, ['careRequest' => $request])
            ->assertSee('Schedule not set')
            ->assertSee('Start and end times missing')
            ->assertSee('Duration unavailable')
            ->assertSee('Posted')
            ->assertDontSee('Next requested visit:')
            ->assertDontSee('Next confirmed visit:');
    }

    private function createRequest(array $overrides = []): CareRequest
    {
        return CareRequest::query()->create(array_merge([
            'family_user_id' => User::factory()->create(['role' => 'family'])->id,
            'title' => 'Schedule detail request',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'address_line1' => '123 Main Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ], $overrides));
    }
}
