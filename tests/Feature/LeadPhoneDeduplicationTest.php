<?php

namespace Tests\Feature;

use App\Jobs\SendLeadWelcomeEmail;
use App\Livewire\Admin\LeadsIndex;
use App\Models\FamilyAccount;
use App\Models\FamilyAcquisitionSetting;
use App\Models\Lead;
use App\Models\LeadWelcomeEmail;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\VoiceAiCall;
use App\Support\LeadPhone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class LeadPhoneDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    public function test_phone_formats_share_an_identity_without_guessing_incomplete_numbers_or_extensions(): void
    {
        foreach (['9195551234', '19195551234', '+19195551234', '(919) 555-1234', '+1 (919) 555-1234'] as $phone) {
            $this->assertSame('+19195551234', LeadPhone::key($phone));
        }
        foreach ([null, '', 'unknown', '5551234', '+1919555123', '9195551234 ext 2', '9195551234x2'] as $phone) {
            $this->assertNull(LeadPhone::key($phone));
        }
        $this->assertSame('+442079460123', LeadPhone::key('+44 20 7946 0123'));
    }

    public function test_repeated_submissions_keep_the_original_stage_owner_and_history(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead(['status' => 'qualified', 'assigned_admin_id' => $owner->id,
            'next_follow_up_at' => now()->addDay(), 'source' => 'google_sheet',
            'data' => ['notes' => 'Original note']]);

        foreach (['+19195551234', '1 (919) 555-1234', '9195551234'] as $phone) {
            $repeat = $this->lead(['phone' => $phone, 'name' => 'New submission', 'email' => 'added@example.test',
                'status' => 'new', 'source' => 'voice_agent', 'data' => ['notes' => 'Another call']]);
            $this->assertSame($lead->id, $repeat->id);
            $this->assertFalse($repeat->wasRecentlyCreated);
        }

        $this->assertDatabaseCount('leads', 1);
        $lead->refresh();
        $this->assertSame('qualified', $lead->status);
        $this->assertSame($owner->id, $lead->assigned_admin_id);
        $this->assertSame('Original contact', $lead->name);
        $this->assertSame('added@example.test', $lead->email);
        $this->assertSame('google_sheet', $lead->source);
        $this->assertNotNull($lead->next_follow_up_at);
        $this->assertSame('Original note', $lead->data['notes']);
        $this->assertSame(3, $lead->activities()->where('body', 'Another call')->count());
    }

    public function test_phone_less_invalid_and_separate_pipeline_leads_are_not_combined(): void
    {
        $this->lead();
        $this->lead(['lead_type' => Lead::TYPE_REFERRAL]);
        foreach ([null, '', 'unknown', '5551234'] as $phone) {
            $this->lead(['phone' => $phone]);
            $this->lead(['phone' => $phone]);
        }
        $this->assertDatabaseCount('leads', 10);
    }

    public function test_voice_lead_and_callback_requests_reuse_the_existing_contact(): void
    {
        config(['voice_agent.internal_api_token' => 'voice-secret']);
        $lead = $this->lead(['status' => 'contacted']);
        $this->withToken('voice-secret')->postJson('/api/internal/voice/leads', [
            'lead_type' => 'family', 'intent' => 'information', 'phone' => '+19195551234', 'notes' => 'Follow-up call',
        ])->assertCreated()->assertJsonPath('lead_id', $lead->id)->assertJsonPath('status', 'contacted');
        $this->withToken('voice-secret')->postJson('/api/internal/voice/callbacks', [
            'lead_type' => 'family', 'phone' => '19195551234', 'callback_time' => 'Tomorrow afternoon',
        ])->assertCreated()->assertJsonPath('lead_id', $lead->id);
        $this->withToken('voice-secret')->postJson('/api/internal/voice/callbacks', [
            'lead_type' => 'family', 'phone' => '9195551234', 'callback_time' => 'Friday morning',
        ])->assertCreated()->assertJsonPath('lead_id', $lead->id);
        $this->assertDatabaseCount('leads', 1);
        $this->assertSame('Friday morning', $lead->fresh()->data['callback_time']);
    }

    public function test_facebook_imports_keep_all_external_identities_even_when_a_replay_has_no_phone(): void
    {
        config(['services.zapier_facebook_leads.webhook_secret' => 'secret']);
        $lead = $this->lead(['external_source' => 'facebook_lead_ads', 'external_id' => 'first', 'status' => 'qualified']);
        $headers = ['X-LoLo-Zapier-Token' => 'secret'];
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), [
            'lead_id' => 'second', 'name' => 'Repeated ad submission', 'phone' => '+19195551234',
        ], $headers)->assertOk()->assertJsonPath('created', false)->assertJsonPath('lead_id', $lead->id);
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), [
            'lead_id' => 'second', 'name' => 'Replayed submission',
        ], $headers)->assertOk()->assertJsonPath('created', false)->assertJsonPath('lead_id', $lead->id);
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), [
            'lead_id' => 'first', 'name' => 'Original submission replay',
        ], $headers)->assertOk()->assertJsonPath('created', false)->assertJsonPath('lead_id', $lead->id);
        $this->assertDatabaseCount('leads', 1);
        $this->assertSame('qualified', $lead->fresh()->status);
    }

    public function test_editing_a_phone_combines_records_and_keeps_the_open_record_id(): void
    {
        $original = $this->lead();
        $edited = $this->lead(['phone' => '9195559999']);
        $original->activities()->create(['type' => 'call', 'summary' => 'Earlier call', 'occurred_at' => now()]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(LeadsIndex::class)
            ->call('openLead', $edited->id)->set('leadForm.phone', '+1 (919) 555-1234')
            ->call('saveLead')->assertHasNoErrors()->assertSet('selectedLeadId', $edited->id);

        $this->assertDatabaseCount('leads', 1);
        $this->assertSame('Earlier call', $edited->activities()->where('type', 'call')->sole()->summary);
        $this->assertSame('+19195551234', $edited->fresh()->phone_key);
    }

    public function test_backfill_combines_existing_history_and_preserves_delivery_reservations(): void
    {
        $lead = $this->lead(['lead_type' => 'referral', 'status' => 'new', 'call_attempt_count' => 1,
            'external_source' => 'google_sheets', 'external_id' => 'row-1']);
        $duplicateId = DB::table('leads')->insertGetId([
            'lead_type' => 'referral', 'phone' => '+19195551234', 'status' => 'qualified',
            'call_attempt_count' => 2, 'do_not_contact_at' => now(),
            'external_source' => 'google_sheets', 'external_id' => 'row-2',
            'data' => json_encode(['provider_outreach' => ['do_not_call' => true]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $duplicate = Lead::findOrFail($duplicateId);
        $activity = $duplicate->activities()->create(['type' => 'call', 'summary' => 'Existing call', 'occurred_at' => now()]);
        $sms = SmsMessage::create(['lead_id' => $duplicateId, 'direction' => 'incoming', 'status' => 'received',
            'from_phone' => '+19195551234', 'to_phone' => '+19195559999', 'body' => 'Call me']);
        $call = VoiceAiCall::create(['to_phone' => '+19195551234', 'metadata' => ['referral_lead_id' => (string) $duplicateId]]);
        LeadWelcomeEmail::create(['lead_id' => $lead->id, 'email' => 'one@example.test', 'status' => 'sent']);
        $welcome = LeadWelcomeEmail::create(['lead_id' => $duplicateId, 'email' => 'two@example.test',
            'status' => 'sent', 'dedupe_key' => hash('sha256', 'two@example.test')]);

        $migration = require database_path('migrations/2026_11_09_120000_deduplicate_crm_leads_by_phone.php');
        $migration->up();
        $migration->up(); // Resuming the backfill is safe and does not double counts.

        $this->assertDatabaseCount('leads', 1);
        $lead->refresh();
        $this->assertSame('qualified', $lead->status);
        $this->assertSame(3, $lead->call_attempt_count);
        $this->assertNotNull($lead->do_not_contact_at);
        $this->assertTrue($lead->data['provider_outreach']['do_not_call']);
        $this->assertSame($lead->id, $activity->fresh()->lead_id);
        $this->assertSame($lead->id, $sms->fresh()->lead_id);
        $this->assertSame($lead->id, $call->fresh()->metadata['referral_lead_id']);
        $this->assertSame($lead->id, Lead::externalIdentity('google_sheets', 'row-2')->sole()->id);
        $this->assertSame(hash('sha256', 'two@example.test'), $welcome->fresh()->dedupe_key);
        $snapshot = $lead->activities()->where('summary', 'Duplicate lead merged by phone')->sole()->metadata;
        $this->assertSame($duplicateId, $snapshot['original_lead']['id']);
        $this->assertSame($welcome->id, $snapshot['original_welcome_emails'][0]['id']);
    }

    public function test_database_constraint_prevents_two_claims_for_the_same_phone_in_a_pipeline(): void
    {
        $this->lead();
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('leads')->insert(['lead_type' => 'family', 'phone' => '19195551234', 'phone_key' => '+19195551234']);
    }

    public function test_duplicate_phone_does_not_queue_another_welcome_email(): void
    {
        FamilyAcquisitionSetting::current()->update(['alerts_enabled' => false, 'welcome_email_enabled' => true]);
        config(['mail.default' => 'smtp']);
        $first = $this->lead(['source' => 'facebook_lead_ad', 'email' => 'original@example.test']);
        $repeat = $this->lead(['source' => 'facebook_lead_ad', 'phone' => '+19195551234', 'email' => 'new@example.test']);

        $this->assertSame($first->id, $repeat->id);
        $this->assertSame($first->welcomeEmail->id, $repeat->welcomeEmail->id);
        $this->assertDatabaseCount('lead_welcome_emails', 1);
        Queue::assertPushed(SendLeadWelcomeEmail::class, 1);
    }

    public function test_matching_phone_never_reassigns_another_households_account(): void
    {
        $first = FamilyAccount::create(['owner_user_id' => User::factory()->create(['role' => 'family'])->id, 'status' => 'active']);
        $second = FamilyAccount::create(['owner_user_id' => User::factory()->create(['role' => 'family'])->id, 'status' => 'active']);
        $lead = $this->lead(['family_account_id' => $first->id]);
        try {
            $this->lead(['phone' => '+19195551234', 'family_account_id' => $second->id]);
            $this->fail('Different family accounts must be reviewed before a merge.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phone', $exception->errors());
        }
        $this->assertDatabaseCount('leads', 1);
        $this->assertSame($first->id, $lead->fresh()->family_account_id);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Original contact', 'phone' => '(919) 555-1234', 'status' => 'new',
        ], $attributes));
    }
}
