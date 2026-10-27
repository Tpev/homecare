<?php

namespace Tests\Feature\Family;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Caregiver\WorkInbox;
use App\Livewire\Family\CareHistory;
use App\Livewire\Family\ManageCareRequest;
use App\Livewire\Family\RegularCareIndex;
use App\Livewire\Family\RequestsIndex;
use App\Models\CareBookingPayment;
use App\Models\CareRequest;
use App\Models\CareRequestConversation;
use App\Models\User;
use App\Services\CareRequests\CareRequestExpirationService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\CaregiverWorkInboxBuilder;
use App\Support\FamilyActionInboxBuilder;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CareRequestExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/New_York', 'marketplace.caregiver_prelaunch_mode' => false]);
        $this->travelTo(Carbon::parse('2026-10-27 12:00:00', 'America/New_York'));
        Notification::fake();
    }

    public function test_threshold_uses_service_end_and_backfills_only_valid_unbooked_one_time_requests(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $due = $this->request($family, ['requested_end_at' => now()->subHours(24)]);
        $old = $this->request($family, ['status' => 'draft', 'requested_start_at' => now()->subMonths(2)->subHours(3), 'requested_end_at' => now()->subMonths(2)]);
        $knownEnd = $this->request($family, ['requested_start_at' => null]);
        $protected = collect([
            ['requested_end_at' => now()->subHours(24)->addSecond()],
            ['requested_end_at' => now()->addDay()],
            ['requested_end_at' => null],
            ['requested_start_at' => now()->subHour()],
            ['request_type' => 'recurring', 'recurring_starts_on' => '2026-01-01'],
            ['status' => 'filled'],
            ['status' => 'cancelled'],
            ['is_system_generated' => true],
            ['first_hire_at' => now()->subDays(3)],
        ])->map(fn ($attributes) => $this->request($family, $attributes));
        $before = $protected->mapWithKeys(fn ($request) => [$request->id => $request->fresh()->getRawOriginal()]);

        $this->artisan('homecare:expire-care-requests --dry-run')->expectsOutput('3 unbooked request(s) eligible for expiration.')->assertSuccessful();
        $this->assertSame('open', $due->fresh()->status);
        $this->artisan('homecare:expire-care-requests')->expectsOutput('3 unbooked request(s) expired and retained in history.')->assertSuccessful();
        $this->assertSame('expired', $due->fresh()->status);
        $this->assertSame('expired', $old->fresh()->status);
        $this->assertSame('expired', $knownEnd->fresh()->status);
        foreach ($protected as $request) {
            $this->assertSame($before[$request->id], $request->fresh()->getRawOriginal());
        }
        $this->artisan('homecare:expire-care-requests')->expectsOutput('0 unbooked request(s) expired and retained in history.')->assertSuccessful();
        $this->assertDatabaseCount('care_requests', 12);
    }

    public function test_all_booking_states_and_released_bookings_and_payments_are_untouched(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        foreach (['scheduled', 'in_progress', 'paused', 'completed', 'reviewed', 'disputed', 'cancelled', 'released'] as $state) {
            $request = $this->request($family);
            $booking = $request->booking()->create([
                'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
                'status' => $state === 'released' ? 'cancelled' : $state,
                'replacement_released_at' => $state === 'released' ? now() : null,
                'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at,
            ]);
            $payment = $booking->payment()->create([
                'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
                'status' => CareBookingPayment::STATUS_FAILED, 'currency' => 'usd', 'last_error' => 'Card needs attention.',
            ]);
            $before = [$request->refresh()->getRawOriginal(), $booking->refresh()->getRawOriginal(), $payment->refresh()->getRawOriginal()];
            $this->assertSame(0, app(CareRequestExpirationService::class)->expireDue());
            $this->assertSame($before, [$request->fresh()->getRawOriginal(), $booking->fresh()->getRawOriginal(), $payment->fresh()->getRawOriginal()]);
        }
        $account = app(FamilyAccountContext::class)->account($family);
        $actions = app(FamilyActionInboxBuilder::class)->buildForAccount($account);
        $this->assertNotEmpty($actions->filter(fn ($item) => str_contains(strtolower($item['title']), 'payment')));
    }

    public function test_history_preserves_applications_messages_and_scoped_family_access(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = $this->request($family, ['title' => 'Expired companionship record']);
        $application = $request->applications()->create(['caregiver_user_id' => $caregiver->id, 'status' => 'shortlisted', 'cover_note' => 'Original application']);
        $conversation = CareRequestConversation::findOrCreateForApplication($application->load('careRequest'), $caregiver->id);
        $message = $conversation->messages()->create(['sender_user_id' => $caregiver->id, 'body' => 'Keep this message.']);
        $invitation = $request->invitations()->create(['family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'pending', 'message' => 'Original invitation']);
        $beforeApplication = $application->refresh()->getRawOriginal();
        $beforeMessage = $message->refresh()->getRawOriginal();

        $this->assertSame(1, app(CareRequestExpirationService::class)->expireDue());
        $this->assertSame($beforeApplication, $application->fresh()->getRawOriginal());
        $this->assertSame($beforeMessage, $message->fresh()->getRawOriginal());
        $this->assertSame('expired', $invitation->fresh()->status);
        $this->assertSame('Original invitation', $invitation->fresh()->message);
        $account = app(FamilyAccountContext::class)->account($family);
        $this->assertEmpty(app(FamilyActionInboxBuilder::class)->buildForAccount($account));

        Livewire::actingAs($family)->test(RequestsIndex::class)->assertViewHas('openRequestCount', 0)->assertDontSee('Resolve the old request');
        Livewire::actingAs($family)->test(RegularCareIndex::class)->assertDontSee('Expired companionship record');
        Livewire::actingAs($family)->test(CareHistory::class)->set('historyView', 'expired')->assertSee('Expired companionship record')->assertSee('1 caregiver reply saved');
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])->assertSee('Expired — service date passed')->assertSee('No action is needed.');
        Livewire::actingAs(User::factory()->create(['role' => 'family']))->test(CareHistory::class)->set('historyView', 'expired')->assertDontSee('Expired companionship record');
    }

    public function test_caregiver_history_replaces_active_applications_and_invitations_without_exposing_other_requests(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $applied = $this->request($family, ['title' => 'Past application']);
        $applied->applications()->create(['caregiver_user_id' => $caregiver->id, 'status' => 'applied']);
        $invited = $this->request($family, ['title' => 'Past private invitation', 'is_private' => true]);
        $invited->invitations()->create(['family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'pending']);
        $this->request($family, ['title' => 'Unrelated expired request']);
        app(CareRequestExpirationService::class)->expireDue();

        $builder = app(CaregiverWorkInboxBuilder::class);
        $this->assertEmpty($builder->buildForUser($caregiver));
        $this->assertSame(0, $builder->countsForUser($caregiver)['applied']);
        $this->assertSame(2, $builder->countsForUser($caregiver)['history']);
        Livewire::actingAs($caregiver)->test(WorkInbox::class)->assertDontSee('Past application')
            ->set('scope', 'history')->assertSee('Past application')->assertSee('Past private invitation')->assertDontSee('Unrelated expired request');
        foreach ([$applied, $invited] as $request) {
            Livewire::actingAs($caregiver)->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
                ->assertSee('Expired — service date passed')->assertDontSee('Waiting for family')->assertDontSee('Send application');
        }
        $stranger = User::factory()->create(['role' => 'caregiver']);
        $this->assertEmpty($builder->buildForUser($stranger, 'history'));
        Livewire::actingAs($stranger)->test(ApplyToCareRequest::class, ['careRequest' => $invited->id])->assertForbidden();
    }

    public function test_eligibility_is_rechecked_after_the_scan_if_service_time_changes(): void
    {
        $request = $this->request(User::factory()->create(['role' => 'family']));
        $changed = false;
        DB::listen(function ($query) use ($request, &$changed): void {
            if (! $changed && str_starts_with($query->sql, 'select "id" from "care_requests"')) {
                $changed = true;
                CareRequest::whereKey($request->id)->update(['requested_end_at' => now()->addDays(2)]);
            }
        });
        $this->assertSame(0, app(CareRequestExpirationService::class)->expireDue());
        $this->assertTrue($changed);
        $this->assertSame('open', $request->fresh()->status);
    }

    public function test_expiration_uses_24_elapsed_hours_across_daylight_saving_and_is_scheduled(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 11:00:00', 'America/New_York'));
        $family = User::factory()->create(['role' => 'family']);
        $due = $this->request($family, ['requested_start_at' => '2026-10-31 09:00:00', 'requested_end_at' => '2026-10-31 12:00:00']);
        $recent = $this->request($family, ['requested_start_at' => '2026-10-31 09:00:00', 'requested_end_at' => '2026-10-31 12:00:01']);
        $this->assertSame(1, app(CareRequestExpirationService::class)->expireDue());
        $this->assertSame('expired', $due->fresh()->status);
        $this->assertSame('open', $recent->fresh()->status);
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'homecare:expire-care-requests'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_existing_backlog_is_processed_across_chunks_without_deleting_records(): void
    {
        $request = $this->request(User::factory()->create(['role' => 'family']));
        $row = $request->fresh()->getRawOriginal();
        unset($row['id']);
        DB::table('care_requests')->insert(array_fill(0, 204, $row));
        $this->assertSame(205, app(CareRequestExpirationService::class)->expireDue());
        $this->assertSame(205, CareRequest::where('status', 'expired')->count());
        $this->assertDatabaseCount('care_requests', 205);
        $this->assertSame(0, app(CareRequestExpirationService::class)->expireDue());
    }

    private function request(User $family, array $attributes = []): CareRequest
    {
        return CareRequest::query()->create(array_merge([
            'family_user_id' => $family->id, 'title' => 'Care request', 'status' => 'open', 'request_type' => 'one_time',
            'requested_start_at' => now()->subDays(3), 'requested_end_at' => now()->subDays(2),
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ], $attributes));
    }
}
