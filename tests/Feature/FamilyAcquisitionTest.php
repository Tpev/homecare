<?php

namespace Tests\Feature;

use App\Livewire\Admin\FamilyAcquisitionOverview;
use App\Livewire\Admin\FamilyLeadsIndex;
use App\Livewire\Sdr\FamilyCallingConsole;
use App\Mail\Ops\FamilyLeadAlertMail;
use App\Models\FamilyAcquisitionSetting;
use App\Models\Lead;
use App\Models\User;
use App\Services\FamilyAcquisition\FamilyLeadAlertService;
use Database\Seeders\FamilyAcquisitionDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_family_acquisition_routes_are_scoped_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sdr = User::factory()->create(['role' => 'sdr']);

        $this->actingAs($admin)->get(route('admin.family-acquisition.overview'))->assertOk();
        $this->actingAs($admin)->get(route('admin.family-acquisition.leads'))->assertOk();
        $this->actingAs($admin)->get(route('sdr.family-calling'))->assertOk();

        $this->actingAs($sdr)
            ->get(route('sdr.family-calling'))
            ->assertOk()
            ->assertSee('Family leads')
            ->assertSee('Referral outreach');

        $this->actingAs($sdr)
            ->get(route('sdr.calling'))
            ->assertOk()
            ->assertSee('Family leads')
            ->assertSee('Referral outreach');
        $this->actingAs($sdr)->get(route('admin.family-acquisition.overview'))->assertForbidden();
        $this->actingAs($sdr)->get(route('admin.family-acquisition.leads'))->assertForbidden();
    }

    public function test_unsuccessful_call_returns_on_the_next_business_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 10:00:00'));
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->familyLead($sdr, ['call_attempt_count' => 0, 'unanswered_attempt_count' => 0]);

        Livewire::actingAs($sdr)
            ->test(FamilyCallingConsole::class)
            ->set('activeLeadId', $lead->id)
            ->call('logOutcome', 'no_answer')
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertSame(1, $lead->call_attempt_count);
        $this->assertSame(1, $lead->unanswered_attempt_count);
        $this->assertSame('attempting_contact', $lead->status);
        $this->assertSame('2026-09-07 12:15:00', $lead->next_follow_up_at?->format('Y-m-d H:i:s'));
        $this->assertNotNull($lead->first_call_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'call',
            'summary' => 'Family call: No answer',
        ]);
    }

    public function test_seventh_unsuccessful_call_closes_the_sequence_as_unreachable(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->familyLead($sdr, ['call_attempt_count' => 6, 'unanswered_attempt_count' => 6, 'status' => 'attempting_contact']);

        Livewire::actingAs($sdr)
            ->test(FamilyCallingConsole::class)
            ->set('activeLeadId', $lead->id)
            ->call('logOutcome', 'voicemail_left')
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertSame(7, $lead->call_attempt_count);
        $this->assertSame(7, $lead->unanswered_attempt_count);
        $this->assertSame('unreachable', $lead->status);
        $this->assertNull($lead->next_follow_up_at);
        $this->assertSame('Unreachable after 7 call attempts', $lead->closed_reason);
    }

    public function test_retry_cadence_varies_call_windows_and_respects_an_afternoon_preference(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->familyLead($sdr, [
            'data' => [
                'form_answers' => [
                    'care_for' => 'Father, 82',
                    'preferred_call_time' => 'After 4pm',
                ],
            ],
        ]);

        Livewire::actingAs($sdr)
            ->test(FamilyCallingConsole::class)
            ->set('activeLeadId', $lead->id)
            ->call('logOutcome', 'no_answer');

        $this->assertSame('2026-09-02 16:30:00', $lead->fresh()->next_follow_up_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow(Carbon::parse('2026-09-02 16:31:00'));
        Livewire::actingAs($sdr)
            ->test(FamilyCallingConsole::class)
            ->set('activeLeadId', $lead->id)
            ->call('logOutcome', 'voicemail_left');

        $lead->refresh();
        $this->assertSame(2, $lead->unanswered_attempt_count);
        $this->assertSame('2026-09-03 11:30:00', $lead->next_follow_up_at?->format('Y-m-d H:i:s'));
    }

    public function test_connected_outcome_records_contact_and_moves_the_family_forward(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->familyLead($sdr);

        Livewire::actingAs($sdr)
            ->test(FamilyCallingConsole::class)
            ->set('activeLeadId', $lead->id)
            ->set('note', 'Family needs weekday companionship and is ready for an assessment.')
            ->call('logOutcome', 'connected_qualified')
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertSame('qualified', $lead->status);
        $this->assertSame(0, $lead->unanswered_attempt_count);
        $this->assertNotNull($lead->first_connected_at);
        $this->assertNull($lead->next_follow_up_at);
    }

    public function test_management_can_enter_a_manual_family_lead_into_the_same_queue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(FamilyLeadsIndex::class)
            ->set('leadForm.name', 'Manual Family')
            ->set('leadForm.phone', '919-555-0199')
            ->set('leadForm.email', 'manual@example.test')
            ->set('leadForm.relationship', 'Daughter')
            ->set('leadForm.care_for', 'Mother, 82')
            ->set('leadForm.care_needs', 'Companionship, Meal preparation')
            ->set('leadForm.urgency', 'This week')
            ->set('leadForm.priority', 'high')
            ->call('createLead')
            ->assertHasNoErrors();

        $lead = Lead::query()->where('email', 'manual@example.test')->firstOrFail();
        $this->assertSame(Lead::TYPE_FAMILY, $lead->lead_type);
        $this->assertSame('manual_crm', $lead->source);
        $this->assertSame('new', $lead->status);
        $this->assertNotNull($lead->submitted_at);
        $this->assertSame(['Companionship', 'Meal preparation'], data_get($lead->data, 'form_answers.care_needs'));
    }

    public function test_seeded_review_accounts_and_all_three_surfaces_render(): void
    {
        $this->seed(FamilyAcquisitionDemoSeeder::class);

        $admin = User::query()->where('email', 'admin.acquisition@lolo.test')->firstOrFail();
        $sdr = User::query()->where('email', 'sdr.family@lolo.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.family-acquisition.overview'))
            ->assertOk()
            ->assertSee('From ad spend to care started.');

        $this->actingAs($admin)
            ->get(route('admin.family-acquisition.leads'))
            ->assertOk()
            ->assertSee('Claire Thompson');

        $this->actingAs($sdr)
            ->get(route('sdr.family-calling'))
            ->assertOk()
            ->assertSee('Family calling console')
            ->assertSee('attempt');
    }

    public function test_admin_can_configure_immediate_and_escalation_email_recipients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(FamilyAcquisitionOverview::class)
            ->set('alertsEnabled', true)
            ->set('newLeadAlertEmails', "first.sdr@example.test\nbackup.sdr@example.test")
            ->set('escalationAlertEmails', 'manager@example.test')
            ->set('firstCallSlaMinutes', 12)
            ->call('saveAlertSettings')
            ->assertHasNoErrors();

        $settings = FamilyAcquisitionSetting::current();
        $this->assertSame(['first.sdr@example.test', 'backup.sdr@example.test'], $settings->newLeadRecipients());
        $this->assertSame(['manager@example.test'], $settings->escalationRecipients());
        $this->assertSame(12, $settings->first_call_sla_minutes);
        $this->assertSame($admin->id, $settings->updated_by_user_id);
    }

    public function test_new_family_lead_email_contains_call_context_and_is_only_sent_once(): void
    {
        Mail::fake();
        FamilyAcquisitionSetting::query()->create([
            'alerts_enabled' => true,
            'new_lead_alert_emails' => 'sdr@example.test',
            'escalation_alert_emails' => 'manager@example.test',
            'first_call_sla_minutes' => 15,
        ]);
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->familyLead($sdr, ['assigned_admin_id' => null]);

        $alerts = app(FamilyLeadAlertService::class);
        $this->assertFalse($alerts->notifyNewLead($lead));

        Mail::assertSent(FamilyLeadAlertMail::class, function (FamilyLeadAlertMail $mail) use ($lead): bool {
            return $mail->hasTo('sdr@example.test')
                && $mail->lead->is($lead)
                && $mail->alertType === FamilyLeadAlertMail::TYPE_NEW;
        });
        $this->assertCount(1, Mail::sent(FamilyLeadAlertMail::class, fn (FamilyLeadAlertMail $mail): bool => $mail->alertType === FamilyLeadAlertMail::TYPE_NEW));
        $this->assertNotNull($lead->fresh()->new_lead_alerted_at);
        $html = (new FamilyLeadAlertMail($lead->fresh()))->render();
        $this->assertStringContainsString('Test Family', $html);
        $this->assertStringContainsString('919-555-0100', $html);
        $this->assertStringContainsString('LoLo Care', $html);
    }

    public function test_uncalled_lead_is_escalated_once_after_the_admin_sla(): void
    {
        Mail::fake();
        FamilyAcquisitionSetting::query()->create([
            'alerts_enabled' => true,
            'new_lead_alert_emails' => 'sdr@example.test',
            'escalation_alert_emails' => 'manager@example.test',
            'first_call_sla_minutes' => 10,
        ]);
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->familyLead($sdr, [
            'assigned_admin_id' => null,
            'submitted_at' => now()->subMinutes(11),
            'first_call_at' => null,
        ]);

        $this->artisan('family-leads:escalate-uncalled')->assertSuccessful();
        $this->artisan('family-leads:escalate-uncalled')->assertSuccessful();

        Mail::assertSent(FamilyLeadAlertMail::class, function (FamilyLeadAlertMail $mail): bool {
            return $mail->hasTo('manager@example.test')
                && $mail->alertType === FamilyLeadAlertMail::TYPE_ESCALATION;
        });
        $this->assertCount(1, Mail::sent(FamilyLeadAlertMail::class, fn (FamilyLeadAlertMail $mail): bool => $mail->alertType === FamilyLeadAlertMail::TYPE_ESCALATION));
        $this->assertNotNull($lead->fresh()->first_call_escalated_at);
    }

    private function familyLead(User $sdr, array $overrides = []): Lead
    {
        return Lead::query()->create(array_merge([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Test Family',
            'phone' => '919-555-0100',
            'status' => 'attempting_contact',
            'priority' => 'normal',
            'source' => 'meta_lead_ads',
            'assigned_admin_id' => $sdr->id,
            'submitted_at' => now()->subMinutes(20),
            'call_attempt_count' => 0,
            'unanswered_attempt_count' => 0,
            'data' => [
                'meta' => ['campaign_id' => 'test-campaign'],
                'form_answers' => ['care_for' => 'Mother, 80'],
            ],
        ], $overrides));
    }
}
