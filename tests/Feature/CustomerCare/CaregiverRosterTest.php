<?php

namespace Tests\Feature\CustomerCare;

use App\Jobs\SendCaregiverRosterEntry;
use App\Livewire\CustomerCare\CaregiverRosterWorkspace;
use App\Models\CaregiverProfile;
use App\Models\CaregiverRoster;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\CustomerCare\CareRosterEvidence;
use App\Services\CustomerCare\CareRosterService;
use App\Services\CustomerCare\ManagedCareService;
use App\Services\Messaging\LeadSmsService;
use App\Services\Messaging\TwilioSmsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 28)->setTime(10, 0));
        config(['marketplace.caregiver_prelaunch_mode' => false, 'services.openai.api_key' => 'test-only',
            'services.openai.base_url' => 'https://api.openai.test/v1', 'services.openai.ca_bundle' => null,
            'services.twilio.sms_from' => '+19845550100']);
        Queue::fake();
        Notification::fake();
        Http::preventStrayRequests();
        $this->fakeAi();
    }

    public function test_only_admin_and_cs_can_open_or_invoke_the_component(): void
    {
        foreach (['admin', 'customer_success'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('customer-care.roster'))->assertOk()->assertSee('Caregiver roster');
        }
        foreach (['family', 'caregiver', 'sales', 'sdr'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('customer-care.roster'))->assertForbidden();
            Livewire::actingAs($actor)->test(CaregiverRosterWorkspace::class)->assertForbidden();
        }
    }

    public function test_generation_reads_uninvited_candidates_notes_and_sms_without_sending(): void
    {
        [$actor, $request, $caregiver] = $this->fixture();
        DB::table('caregiver_crm_activities')->insert(['caregiver_user_id' => $caregiver->id, 'actor_user_id' => $actor->id,
            'type' => 'note', 'body' => 'Confirm two-hour visits; previously asked about travel.', 'occurred_at' => now(), 'created_at' => now()]);
        $message = SmsMessage::create(['caregiver_user_id' => $caregiver->id, 'direction' => 'incoming', 'status' => 'received',
            'body' => 'Monday afternoon works; please confirm the tasks.', 'from_phone' => $caregiver->phone, 'to_phone' => '+19845550100']);
        $roster = $this->generate($actor, $request);
        $this->assertSame('ready', $roster->status, $roster->error ?? 'Generation failed');
        $this->assertSame(1, $roster->entries()->count());
        $this->assertSame('invite', $roster->entries->first()->action);
        $refs = collect($roster->entries->first()->evidence['caregiver']['records'])->pluck('ref');
        $this->assertContains('sms_messages:'.$message->id, $refs);
        Http::assertSent(fn ($req) => str_contains($req['input'], 'Confirm two-hour visits') && str_contains($req['input'], 'Monday afternoon works'));
        $this->assertDatabaseCount('care_request_invitations', 0);
        $this->assertDatabaseCount('sms_messages', 1);
        Queue::assertNotPushed(SendCaregiverRosterEntry::class);
        Notification::assertNothingSent();
    }

    public function test_history_stays_small_and_uses_an_index_with_large_saved_drafts(): void
    {
        [$actor, $request] = $this->fixture();
        [, $otherRequest] = $this->fixture();
        $evidence = ['pool' => ['notes' => str_repeat('Private caregiver evidence. ', 12000)], 'assessments' => []];
        $ids = [];
        for ($i = 0; $i < 12; $i++) {
            $ids[] = CaregiverRoster::create([
                'care_request_id' => $request->id, 'created_by_user_id' => $actor->id,
                'status' => 'generating', 'request_fingerprint' => str_repeat('a', 64),
                'context' => 'Saved private search context.', 'generation_data' => $evidence,
                'created_at' => now()->subMinutes(12 - intdiv($i, 2)),
            ])->id;
        }
        CaregiverRoster::create(['care_request_id' => $otherRequest->id, 'created_by_user_id' => $actor->id,
            'status' => 'queued', 'request_fingerprint' => str_repeat('b', 64)]);

        DB::enableQueryLog();
        $component = Livewire::actingAs($actor)->test(CaregiverRosterWorkspace::class)
            ->set('requestId', (string) $request->id)
            ->assertViewHas('history', function ($history) use ($ids) {
                $this->assertCount(10, $history);
                foreach ($history as $past) {
                    $this->assertEmpty(array_intersect(['generation_data', 'context', 'summary', 'questions', 'coverage'], array_keys($past->getAttributes())),
                        'History navigation must not fetch large evidence or draft content.');
                }
                $this->assertSame(array_reverse(array_slice($ids, -10)), $history->modelKeys());

                return true;
            });
        $historyQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'caregiver_rosters') && str_contains($query['query'], 'order by'));
        DB::disableQueryLog();
        $this->assertCount(1, $historyQueries);
        $query = $historyQueries->first();
        if (DB::getDriverName() === 'sqlite') {
            $plan = collect(DB::select('EXPLAIN QUERY PLAN '.$query['query'], $query['bindings']))->pluck('detail')->implode(' ');
            $this->assertStringContainsString('USING COVERING INDEX caregiver_rosters_request_history_index', $plan);
            $this->assertStringNotContainsString('USE TEMP B-TREE', $plan);
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $plan = DB::select('EXPLAIN '.$query['query'], $query['bindings'])[0];
            $this->assertSame('caregiver_rosters_request_history_index', $plan->key);
            $this->assertStringContainsString('Using index', $plan->Extra);
            $this->assertStringNotContainsString('filesort', $plan->Extra);
        }

        $component->call('loadRoster', end($ids))->assertHasNoErrors()
            ->assertSet('context', 'Saved private search context.');
        $this->assertSame($evidence, CaregiverRoster::findOrFail(end($ids))->generation_data);
        Queue::assertNotPushed(SendCaregiverRosterEntry::class);
        Http::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_cs_reviews_exact_edited_messages_and_approval_is_idempotent(): void
    {
        [$actor, $request, $caregiver] = $this->fixture();
        $roster = $this->generate($actor, $request);
        $entry = $roster->entries->first();
        $component = Livewire::actingAs($actor)->test(CaregiverRosterWorkspace::class)->call('loadRoster', $roster->id)
            ->assertSee($caregiver->name)->set('drafts.'.$entry->id.'.selected', true)
            ->set('drafts.'.$entry->id.'.invitation_body', 'Reviewed invitation for Monday.')
            ->call('review')->assertHasNoErrors()->assertSee('Review exact outreach')->assertSee('Reviewed invitation for Monday.');
        $this->assertDatabaseCount('care_request_invitations', 0);
        $token = $roster->fresh()->review_token;
        $component->call('approve')->assertHasNoErrors();
        app(CareRosterService::class)->approve($actor, $roster, $token);
        Queue::assertPushed(SendCaregiverRosterEntry::class, 1);
        app(CareRosterService::class)->dispatch($entry->id);
        app(CareRosterService::class)->dispatch($entry->id);
        $this->assertDatabaseHas('care_request_invitations', ['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'message' => 'Reviewed invitation for Monday.', 'invited_by_user_id' => $actor->id]);
        $this->assertDatabaseCount('care_request_invitations', 1);
        $this->assertDatabaseCount('sms_messages', 0);
        Notification::assertNotSentTo($request->family, \App\Notifications\MarketplaceEventNotification::class);
        $this->assertSame('completed', $entry->fresh()->dispatch_status);
    }

    public function test_optional_sms_uses_existing_sender_and_is_not_resent(): void
    {
        [$actor, $request] = $this->fixture();
        $roster = $this->generate($actor, $request);
        $entry = $roster->entries->first();
        $this->mock(TwilioSmsClient::class)->shouldReceive('sendMessage')->once()
            ->withArgs(fn ($to, $body) => $body === 'Reviewed SMS for Monday.')
            ->andReturn(['sid' => 'SMroster001', 'status' => 'queued']);
        $this->approve($actor, $roster, true);
        app(CareRosterService::class)->dispatch($entry->id);
        app(CareRosterService::class)->dispatch($entry->id);
        $this->assertDatabaseCount('sms_messages', 1);
        $this->assertDatabaseHas('sms_messages', ['body' => 'Reviewed SMS for Monday.', 'sent_by_user_id' => $actor->id]);
        $this->assertSame('completed', $entry->fresh()->dispatch_status);
        $this->assertNotNull($entry->fresh()->sms_message_id);
    }

    public function test_follow_up_never_reinvites_and_requires_explicit_sms_choice(): void
    {
        [$actor, $request, $caregiver] = $this->fixture();
        $request->invitations()->create(['family_account_id' => $request->family_account_id, 'family_user_id' => $request->family_user_id,
            'caregiver_user_id' => $caregiver->id, 'invited_by_user_id' => $actor->id, 'status' => 'pending', 'expires_at' => now()->addDays(3)]);
        $roster = $this->generate($actor, $request);
        $this->assertSame('follow_up', $roster->entries->first()->action);
        try {
            $this->approve($actor, $roster, false);
            $this->fail('An implicit follow-up was accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Select SMS', $e->getMessage());
        }
        $this->mock(TwilioSmsClient::class)->shouldReceive('sendMessage')->once()->andReturn(['sid' => 'SMfollowup', 'status' => 'queued']);
        $this->approve($actor, $roster, true);
        app(CareRosterService::class)->dispatch($roster->entries->first()->id);
        $this->assertDatabaseCount('care_request_invitations', 1);
        $this->assertDatabaseCount('sms_messages', 1);
    }

    public function test_changed_request_blocks_approval(): void
    {
        [$actor, $request] = $this->fixture();
        $roster = $this->generate($actor, $request);
        $token = app(CareRosterService::class)->prepare($actor, $roster, $this->drafts($roster));
        $request->update(['scope_of_work' => 'Different tasks now.']);
        $this->expectException(ValidationException::class);
        app(CareRosterService::class)->approve($actor, $roster, $token);
    }

    public function test_expired_or_replaced_preview_cannot_send(): void
    {
        [$actor, $request] = $this->fixture();
        $roster = $this->generate($actor, $request);
        $service = app(CareRosterService::class);
        $old = $service->prepare($actor, $roster, $this->drafts($roster));
        $new = $service->prepare($actor, $roster, $this->drafts($roster));
        try {
            $service->approve($actor, $roster, $old);
            $this->fail('Stale preview accepted');
        } catch (ValidationException) {
            Queue::assertNotPushed(SendCaregiverRosterEntry::class);
        }
        $this->travel(16)->minutes();
        $this->expectException(ValidationException::class);
        $service->approve($actor, $roster, $new);
    }

    public function test_revoked_approver_or_suspended_caregiver_stops_dispatch(): void
    {
        foreach (['revoked', 'suspended'] as $case) {
            [$actor, $request, $caregiver] = $this->fixture();
            $roster = $this->generate($actor, $request);
            $this->approve($actor, $roster);
            if ($case === 'revoked') {
                $actor->forceFill(['access_revoked_at' => now()])->save();
            } else {
                $roster->entries->first()->caregiver->caregiverProfile->update(['screening_suspended_at' => now()]);
            }
            app(CareRosterService::class)->dispatch($roster->entries->first()->id);
            $this->assertSame('blocked', $roster->entries->first()->fresh()->dispatch_status);
        }
        $this->assertDatabaseCount('care_request_invitations', 0);
        $this->assertDatabaseCount('sms_messages', 0);
    }

    public function test_sms_consent_and_new_stop_reply_are_checked_after_approval(): void
    {
        [$actor, $request, $caregiver] = $this->fixture();
        $roster = $this->generate($actor, $request);
        $this->approve($actor, $roster, true);
        SmsMessage::create(['caregiver_user_id' => $caregiver->id, 'direction' => 'incoming', 'status' => 'received', 'body' => 'STOP', 'from_phone' => $caregiver->phone, 'to_phone' => '+19845550100']);
        app(CareRosterService::class)->dispatch($roster->entries->first()->id);
        $this->assertSame('blocked', $roster->entries->first()->fresh()->dispatch_status);
        $this->assertDatabaseCount('care_request_invitations', 0);
        $this->assertSame(0, SmsMessage::where('direction', 'outgoing')->count());
    }

    public function test_foreign_entry_and_missing_preview_cannot_be_approved(): void
    {
        [$actor, $request] = $this->fixture();
        $roster = $this->generate($actor, $request);
        try {
            app(CareRosterService::class)->prepare($actor, $roster, [['id' => 999999, 'send_sms' => false, 'invitation_body' => 'Hello', 'sms_body' => 'Hello']]);
            $this->fail('Foreign entry accepted');
        } catch (ValidationException) {
            $this->assertNull($roster->fresh()->approved_at);
        }
        $this->expectException(ValidationException::class);
        app(CareRosterService::class)->approve($actor, $roster, '');
    }

    public function test_invalid_provider_result_fails_without_partial_roster_or_sends(): void
    {
        [$actor, $request] = $this->fixture();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['api.openai.test/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '{"assessments":[]}']]]]])]);
        $roster = $this->generate($actor, $request);
        $this->assertSame('failed', $roster->status);
        $this->assertSame(0, $roster->entries()->count());
        $this->assertDatabaseCount('care_request_invitations', 0);
        $this->assertDatabaseCount('sms_messages', 0);
    }

    public function test_staff_sms_bridge_does_not_widen_general_crm_access(): void
    {
        [$actor, , $caregiver] = $this->fixture();
        $this->assertFalse($actor->canAccessCaregiverCrm());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(LeadSmsService::class)->send($caregiver, $actor, 'Must not send', (string) Str::uuid());
    }

    public function test_new_booking_conflict_after_review_stops_approval(): void
    {
        [$actor, $request, $caregiver] = $this->fixture();
        $roster = $this->generate($actor, $request);
        $token = app(CareRosterService::class)->prepare($actor, $roster, $this->drafts($roster));
        $other = $request->replicate(['staff_request_key']);
        $other->fill(['title' => 'Another commitment', 'status' => 'filled'])->save();
        \App\Models\CareBooking::create(['care_request_id' => $other->id, 'family_user_id' => $request->family_user_id,
            'family_account_id' => $request->family_account_id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at]);
        $this->expectException(ValidationException::class);
        app(CareRosterService::class)->approve($actor, $roster, $token);
    }

    public function test_new_reply_after_preview_requires_a_new_evidence_review(): void
    {
        [$actor, $request, $caregiver] = $this->fixture();
        $roster = $this->generate($actor, $request);
        $token = app(CareRosterService::class)->prepare($actor, $roster, $this->drafts($roster));
        SmsMessage::create(['caregiver_user_id' => $caregiver->id, 'direction' => 'incoming', 'status' => 'received',
            'body' => 'My availability changed; please call.', 'from_phone' => $caregiver->phone, 'to_phone' => '+19845550100']);
        $this->expectException(ValidationException::class);
        app(CareRosterService::class)->approve($actor, $roster, $token);
    }

    public function test_provider_timeout_records_uncertain_sms_without_resending(): void
    {
        [$actor, $request] = $this->fixture();
        $roster = $this->generate($actor, $request);
        $entry = $roster->entries->first();
        $this->mock(TwilioSmsClient::class)->shouldReceive('sendMessage')->once()->andThrow(new \RuntimeException('Synthetic timeout'));
        $this->approve($actor, $roster, true);
        app(CareRosterService::class)->dispatch($entry->id);
        app(CareRosterService::class)->dispatch($entry->id);
        $this->assertDatabaseCount('sms_messages', 1);
        $this->assertSame(SmsMessage::STATUS_CONFIRMATION_PENDING, $entry->fresh()->smsMessage->status);
    }

    public function test_customer_evidence_is_linked_by_account_and_dates_are_preserved(): void
    {
        [$actor, $request] = $this->fixture();
        $lead = \App\Models\Lead::create(['family_account_id' => $request->family_account_id, 'lead_type' => 'family', 'name' => 'Linked customer', 'status' => 'new']);
        $other = \App\Models\Lead::create(['lead_type' => 'family', 'name' => 'Unrelated same-name customer', 'status' => 'new']);
        foreach ([$lead, $other] as $item) {
            DB::table('lead_activities')->insert(['lead_id' => $item->id, 'actor_user_id' => $actor->id, 'type' => 'note',
                'body' => 'PRIVATE '.$item->id, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $packet = app(CareRosterEvidence::class)->customer($request);
        $notes = collect($packet['records'])->filter(fn ($r) => str_starts_with($r['ref'], 'lead_activities:'));
        $this->assertCount(1, $notes);
        $this->assertSame('PRIVATE '.$lead->id, $notes->first()['body']);
        $this->assertStringContainsString('2026-11-28', $notes->first()['occurred_at']);
    }

    public function test_unknown_ai_evidence_id_is_rejected(): void
    {
        [$actor, $request] = $this->fixture();
        Http::swap(new \Illuminate\Http\Client\Factory);
        $this->fakeAi('sms_messages:99999999');
        $roster = $this->generate($actor, $request);
        $this->assertSame('failed', $roster->status);
        $this->assertSame(0, $roster->entries()->count());
    }

    public function test_new_customer_note_invalidates_an_existing_roster(): void
    {
        [$actor, $request] = $this->fixture();
        $roster = $this->generate($actor, $request);
        $token = app(CareRosterService::class)->prepare($actor, $roster, $this->drafts($roster));
        app(ManagedCareService::class)->note($actor, $request, 'Customer changed the care needs. Confirm before outreach.');
        $this->expectException(ValidationException::class);
        app(CareRosterService::class)->approve($actor, $roster, $token);
    }

    public function test_retry_recovers_recorded_sms_attempt_instead_of_resending(): void
    {
        [$actor, $request, $caregiver] = $this->fixture();
        $roster = $this->generate($actor, $request);
        $this->approve($actor, $roster, true);
        $entry = $roster->entries->first();
        $message = SmsMessage::create(['caregiver_user_id' => $caregiver->id, 'sent_by_user_id' => $actor->id,
            'direction' => 'outgoing', 'status' => SmsMessage::STATUS_CONFIRMATION_PENDING, 'body' => $entry->fresh()->sms_body,
            'request_key' => $entry->sms_request_key, 'from_phone' => '+19845550100', 'to_phone' => $caregiver->phone]);
        $this->mock(TwilioSmsClient::class)->shouldNotReceive('sendMessage');
        app(CareRosterService::class)->dispatch($entry->id);
        $this->assertSame($message->id, $entry->fresh()->sms_message_id);
        $this->assertSame('completed', $entry->fresh()->dispatch_status);
        $this->assertDatabaseCount('sms_messages', 1);
    }

    public function test_generation_resumes_saved_batches_and_clears_working_evidence(): void
    {
        [$actor, $request] = $this->fixture();
        $service = app(CareRosterService::class);
        $roster = $service->start($actor, $request, 'Confirm the hours.', 'normal');
        $job = new \App\Jobs\GenerateCaregiverRoster($roster->id);
        $job->handle($service);
        $this->assertSame('generating', $roster->fresh()->status);
        $this->assertCount(0, $roster->fresh()->generation_data['assessments']);
        Http::assertNothingSent();
        Queue::assertPushed(\App\Jobs\GenerateCaregiverRoster::class, 2);
        $job->handle($service);
        $this->assertCount(1, $roster->fresh()->generation_data['assessments']);
        $this->assertSame(0, $roster->entries()->count());
        $job->handle($service);
        $job->handle($service);
        $this->assertSame('ready', $roster->fresh()->status);
        $this->assertNull($roster->fresh()->generation_data);
        $this->assertSame(1, $roster->entries()->count());
        Queue::assertNotPushed(SendCaregiverRosterEntry::class);
    }

    private function fixture(): array
    {
        $actor = User::factory()->create(['role' => 'customer_success']);
        $customer = User::factory()->create(['role' => 'family']);
        $request = app(ManagedCareService::class)->save($actor, $customer, [
            'request_key' => (string) Str::uuid(), 'title' => 'Bathing assistance', 'scope_of_work' => 'Bathing and dressing help.',
            'recipient_name' => 'Synthetic recipient', 'address_line1' => '100 Test Street', 'city' => 'Durham', 'state' => 'NC', 'zip' => '27704',
            'request_type' => 'one_time', 'requested_start_at' => now()->addDays(2)->setTime(15, 0)->toDateTimeString(),
            'requested_end_at' => now()->addDays(2)->setTime(17, 0)->toDateTimeString(), 'status' => 'open',
            'tasks' => [CareTask::firstOrCreate(['name' => 'Bathing'])->id],
        ]);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Synthetic Caregiver '.Str::random(4), 'city' => 'Durham', 'state' => 'NC',
            'phone' => '+1984555'.random_int(1000, 9999), 'sms_opt_in' => true]);
        $profile = CaregiverProfile::create(['user_id' => $caregiver->id, 'slug' => 'roster-test-'.$caregiver->id,
            'status' => 'active', 'screening_initial_approved_at' => now(), 'bio' => 'Experienced with bathing and transfers.',
            'years_experience' => 5, 'service_area_zip' => '27704', 'service_radius_miles' => 20,
            'is_accepting_new_clients' => true, 'identity_verified_at' => now()]);
        $profile->skills()->sync([Skill::firstOrCreate(['name' => 'Personal care'])->id]);
        $profile->languages()->sync([Language::firstOrCreate(['name' => 'English'])->id]);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '18:00']);

        return [$actor, $request, $caregiver];
    }

    private function generate(User $actor, CareRequest $request): CaregiverRoster
    {
        $service = app(CareRosterService::class);
        // Directly exercise collection too, so schema failures are visible in tests.
        app(CareRosterEvidence::class)->customer($request);
        app(CareRosterEvidence::class)->pool($request);
        $roster = $service->start($actor, $request, 'Confirm availability and qualification documents.', 'urgent');
        for ($step = 0; $step < 30 && $service->generate($roster->id); $step++) {
            // Queue is faked: execute the same bounded generation steps as the worker.
        }

        return $roster->fresh();
    }

    private function drafts(CaregiverRoster $roster, bool $sms = false): array
    {
        return [['id' => $roster->entries->first()->id, 'send_sms' => $sms,
            'invitation_body' => 'Reviewed invitation for Monday.', 'sms_body' => 'Reviewed SMS for Monday.']];
    }

    private function approve(User $actor, CaregiverRoster $roster, bool $sms = false): void
    {
        $service = app(CareRosterService::class);
        $token = $service->prepare($actor, $roster, $this->drafts($roster, $sms));
        $service->approve($actor, $roster, $token);
    }

    private function fakeAi(?string $evidenceOverride = null): void
    {
        Http::fake(['api.openai.test/*' => function ($request) use ($evidenceOverride) {
            $input = json_decode($request['input'], true);
            if (isset($input['candidates'])) {
                $data = ['assessments' => array_map(fn ($candidate) => [
                    'caregiver_user_id' => $candidate['caregiver_user_id'], 'fit' => 'strong', 'why' => 'Relevant bathing experience and saved afternoon hours.',
                    'concerns' => ['Saved hours still need confirmation.'], 'confirm' => ['Can you commit to both visits?'],
                    'evidence_refs' => [$evidenceOverride ?? $candidate['records'][0]['ref']], 'avoid_contact' => false,
                    'invitation_body' => 'Are you interested in bathing assistance in Durham on Monday afternoon?',
                    'sms_body' => 'LoLo Care: please review your invitation for Monday afternoon in Durham.',
                ], $input['candidates'])];
            } else {
                $data = ['summary' => 'Review these caregivers before outreach.', 'customer_questions' => ['Is the timing flexible?'],
                    'caregiver_user_ids' => array_column($input['assessments'], 'caregiver_user_id')];
            }

            return Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($data)]]]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 100]]);
        }]);
    }
}
