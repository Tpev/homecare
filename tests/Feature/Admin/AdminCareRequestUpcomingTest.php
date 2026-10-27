<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CareRequestsIndex;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\User;
use App\Support\AdminCareRequestUpcomingPaginator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCareRequestUpcomingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/New_York', 'marketplace.caregiver_prelaunch_mode' => false]);
        $this->travelTo(Carbon::parse('2026-10-19 08:00:00', 'America/New_York'));
    }

    public function test_upcoming_ranks_all_results_before_pagination_and_preserves_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $requests = collect(range(29, 1))->map(fn (int $days) => $this->request($family, [
            'title' => 'Upcoming '.$days,
            'requested_start_at' => now()->addDays($days)->setTime(9, 0),
            'requested_end_at' => now()->addDays($days)->setTime(12, 0),
        ]))->reverse()->values();
        $this->request($family, ['status' => 'draft', 'title' => 'Filtered draft']);
        $this->request($family, ['is_system_generated' => true, 'title' => 'Hidden generated visit']);

        Livewire::actingAs($admin)->test(CareRequestsIndex::class)
            ->set('status', 'open')
            ->set('sort', 'start_soon')
            ->assertViewHas('requests', fn ($page) => $page->total() === 29
                && $page->getCollection()->pluck('id')->all() === $requests->take(25)->pluck('id')->all())
            ->call('setPage', 2)
            ->assertViewHas('requests', fn ($page) => $page->getCollection()->pluck('id')->all()
                === $requests->skip(25)->pluck('id')->all())
            ->set('q', 'Upcoming 29')
            ->assertSet('paginators.page', 1)
            ->assertViewHas('requests', fn ($page) => $page->total() === 1
                && $page->first()->id === $requests->last()->id);
    }

    public function test_missing_past_and_closed_schedules_sort_after_upcoming_without_posted_date_fallback(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $missing = $this->request($family, ['requested_start_at' => null, 'requested_end_at' => null]);
        $past = $this->request($family, ['requested_start_at' => now()->subDays(5), 'requested_end_at' => now()->subDays(5)->addHours(2)]);
        $closed = $this->request($family, ['status' => 'cancelled']);
        $upcoming = $this->request($family, ['requested_start_at' => now()->addDays(4), 'requested_end_at' => now()->addDays(4)->addHours(2)]);

        $page = app(AdminCareRequestUpcomingPaginator::class)->paginate(CareRequest::query(), 2, 1);
        $this->assertSame([$upcoming->id, $closed->id], $page->pluck('id')->all());
        $this->assertSame(4, $page->total());
        $second = app(AdminCareRequestUpcomingPaginator::class)->paginate(CareRequest::query(), 2, 2);
        $this->assertSame([$past->id, $missing->id], $second->pluck('id')->all());
    }

    public function test_next_recurring_visit_competes_with_confirmed_booking_not_original_request_time(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $booked = $this->request($family, ['status' => 'filled']);
        CareBooking::query()->create([
            'care_request_id' => $booked->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->addDays(3)->setTime(10, 0),
            'scheduled_end_at' => now()->addDays(3)->setTime(12, 0),
        ]);
        $recurring = $this->request($family, [
            'request_type' => 'recurring',
            'requested_start_at' => null, 'requested_end_at' => null,
            'recurring_starts_on' => '2026-10-01',
            'recurring_schedule' => [['day' => 2, 'start_time' => '09:00', 'end_time' => '12:30']],
        ]);
        $between = $this->request($family, [
            'requested_start_at' => now()->addDays(2),
            'requested_end_at' => now()->addDays(2)->addHours(1),
        ]);

        $page = app(AdminCareRequestUpcomingPaginator::class)->paginate(CareRequest::query(), 25, 1);
        $this->assertSame([$recurring->id, $between->id, $booked->id], $page->pluck('id')->all());
    }

    public function test_empty_upcoming_results_keep_a_valid_paginator(): void
    {
        $page = app(AdminCareRequestUpcomingPaginator::class)->paginate(CareRequest::query(), 25, 1);
        $this->assertSame(0, $page->total());
        $this->assertCount(0, $page);
        $this->assertSame(1, $page->currentPage());
    }

    private function request(User $family, array $attributes = []): CareRequest
    {
        return CareRequest::query()->create(array_merge([
            'family_user_id' => $family->id,
            'title' => 'Care request',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay(),
            'requested_end_at' => now()->addDay()->addHours(2),
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ], $attributes));
    }
}
