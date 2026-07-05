<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\CallbackRequest;
use App\Livewire\Family\HomepageQuickRequest;
use App\Mail\Ops\CallbackRequestOpsAlertMail;
use App\Models\CareTask;
use App\Models\Lead;
use App\Models\User;
use App\Support\FamilyQuickRequestDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class HomepageQuickRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_direct_message_and_quick_request_entry(): void
    {
        CareTask::query()->create(['name' => 'Companionship']);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSeeText('Care when you')
            ->assertSeeText('need it most.')
            ->assertSeeText('Call or text (984) 400-4008')
            ->assertSeeText('Request a callback')
            ->assertSee('Tell us what you need');
    }

    public function test_guest_can_continue_homepage_quick_request_to_callback_page(): void
    {
        $component = Livewire::test(HomepageQuickRequest::class)
            ->set('service_type', 'Companion care')
            ->set('zip', '27601')
            ->set('time_preference', 'today_afternoon');

        $component->call('continueToCallback')
            ->assertRedirect(route('landing.get-care', [
                'service_type' => 'Companion care',
                'zip' => '27601',
                'time_preference' => 'today_afternoon',
            ], absolute: false));
    }

    public function test_callback_page_creates_family_callback_lead(): void
    {
        config([
            'marketplace.ops_alert_recipients' => [
                'peverelli.t@gmail.com',
                'cpetrinipoli@hub.healthcare',
            ],
        ]);
        Mail::fake();

        $this->get(route('landing.get-care'))
            ->assertOk()
            ->assertSeeText('Find trusted home care support')
            ->assertSeeText('What kind of help would be most useful first?');

        Livewire::test(CallbackRequest::class)
            ->assertSet('step', 1)
            ->call('choose', 'service_type', 'Companion care')
            ->assertSet('step', 2)
            ->call('choose', 'recipient_relationship', 'Parent or older relative')
            ->assertSet('step', 3)
            ->call('choose', 'start_time', 'this_week')
            ->assertSet('step', 4)
            ->call('choose', 'visit_frequency', 'few_times_week')
            ->assertSet('step', 5)
            ->call('choose', 'visit_length', 'two_to_three_hours')
            ->assertSet('step', 6)
            ->call('choose', 'callback_time', 'tomorrow_morning')
            ->assertSet('step', 7)
            ->set('full_name', 'Jane Family')
            ->set('phone', '(984) 400-0000')
            ->set('email', 'jane@example.com')
            ->set('zip', '27601')
            ->set('notes', 'My mom needs companionship twice a week.')
            ->set('sms_opt_in', true)
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertDispatched('lolo-callback-submitted');

        $lead = Lead::query()->sole();

        $this->assertSame('family', $lead->lead_type);
        $this->assertSame('Jane Family', $lead->name);
        $this->assertSame('(984) 400-0000', $lead->phone);
        $this->assertSame('27601', $lead->zip);
        $this->assertSame('callback_request', $lead->data['intent']);
        $this->assertSame('Companion care', $lead->data['service_type']);
        $this->assertSame('Parent or older relative', $lead->data['recipient_relationship']);
        $this->assertSame('This week', $lead->data['start_time_label']);
        $this->assertSame('A few times a week', $lead->data['visit_frequency_label']);
        $this->assertSame('2-3 hours', $lead->data['visit_length_label']);
        $this->assertSame('Tomorrow morning', $lead->data['callback_time_label']);
        $this->assertTrue($lead->data['consent_to_contact']);
        $this->assertTrue($lead->data['phone_callback_requested']);
        $this->assertTrue($lead->data['consent_to_call']);
        $this->assertTrue($lead->data['sms_opt_in']);
        $this->assertSame(Lead::PRIORITY_NORMAL, $lead->priority);

        Mail::assertSent(CallbackRequestOpsAlertMail::class, function (CallbackRequestOpsAlertMail $mail) use ($lead) {
            return $mail->lead->is($lead)
                && $mail->hasTo('peverelli.t@gmail.com')
                && $mail->hasTo('cpetrinipoli@hub.healthcare')
                && str_contains($mail->render(), 'My mom needs companionship twice a week.');
        });
    }

    public function test_callback_page_preserves_paid_social_tracking_on_lead(): void
    {
        config(['marketplace.ops_alert_recipients' => []]);
        Mail::fake();

        Livewire::withQueryParams([
            'utm_source' => 'meta',
            'utm_medium' => 'paid_social',
            'utm_campaign' => 'Lolo_Care_Callback_Wake_County',
            'utm_term' => 'Adult_Children_35_64',
            'utm_content' => 'Short_Visits_Relief',
            'fbclid' => 'IwAR-test-click-id',
        ])
            ->test(CallbackRequest::class)
            ->set('full_name', 'Sam Family')
            ->set('phone', '(984) 400-1234')
            ->set('email', 'sam@example.com')
            ->set('zip', '27607')
            ->set('service_type', 'Errands and rides')
            ->set('recipient_relationship', 'Parent or older relative')
            ->set('start_time', 'asap')
            ->set('visit_frequency', 'one_visit')
            ->set('visit_length', 'half_day')
            ->set('callback_time', 'today')
            ->call('submit')
            ->assertSet('submitted', true);

        $lead = Lead::query()->sole();

        $this->assertSame('meta_ads', $lead->source);
        $this->assertSame('meta_ads', $lead->external_source);
        $this->assertSame('Lolo_Care_Callback_Wake_County / Adult_Children_35_64 / Short_Visits_Relief', $lead->source_detail);
        $this->assertSame('meta', $lead->data['tracking']['utm_source']);
        $this->assertSame('paid_social', $lead->data['tracking']['utm_medium']);
        $this->assertSame('IwAR-test-click-id', $lead->data['tracking']['fbclid']);
        $this->assertFalse($lead->data['sms_opt_in']);
        $this->assertSame(Lead::PRIORITY_HIGH, $lead->priority);
    }

    public function test_callback_page_can_be_submitted_without_sms_opt_in(): void
    {
        config(['marketplace.ops_alert_recipients' => []]);
        Mail::fake();

        Livewire::test(CallbackRequest::class)
            ->set('full_name', 'No Sms Family')
            ->set('phone', '(984) 400-2222')
            ->set('zip', '27601')
            ->set('service_type', 'Meal prep')
            ->set('recipient_relationship', 'Myself')
            ->set('start_time', 'this_week')
            ->set('visit_frequency', 'one_visit')
            ->set('visit_length', 'two_to_three_hours')
            ->set('callback_time', 'today')
            ->set('sms_opt_in', false)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            ->assertDispatched('lolo-callback-submitted');

        $lead = Lead::query()->sole();

        $this->assertSame('callback_request', $lead->data['intent']);
        $this->assertTrue($lead->data['phone_callback_requested']);
        $this->assertTrue($lead->data['consent_to_call']);
        $this->assertFalse($lead->data['sms_opt_in']);
    }

    public function test_family_request_wizard_prefills_from_homepage_quick_request_draft(): void
    {
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family);
        session([
            FamilyQuickRequestDraft::SESSION_KEY => [
                'request_mode' => CreateCareRequestWizard::MODE_FAST_TRACK,
                'modeChosen' => true,
                'step' => 4,
                'request_type' => 'one_time',
                'recipient_full_name' => 'Margaret Johnson',
                'selectedTasks' => [$task->id],
                'additional_info' => 'Lunch and companionship.',
                'requested_start_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
                'requested_end_at' => now()->addDay()->setTime(13, 0)->format('Y-m-d H:i:s'),
                'address_line1' => '101 Oak Street',
                'city' => 'Raleigh',
                'state' => 'NC',
                'zip' => '27601',
            ],
        ]);

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->assertSet('modeChosen', true)
            ->assertSet('step', 4)
            ->assertSet('recipient_full_name', 'Margaret Johnson')
            ->assertSet('selectedTasks', [$task->id])
            ->assertSet('city', 'Raleigh')
            ->assertSet('zip', '27601');
    }

    public function test_registration_redirects_to_request_wizard_when_quick_request_draft_exists(): void
    {
        session([
            FamilyQuickRequestDraft::SESSION_KEY => [
                'recipient_full_name' => 'Margaret Johnson',
            ],
        ]);

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('phone', '(984) 400-4008')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true);

        $component->call('register')
            ->assertRedirect(route('family.requests.create', absolute: false));
    }

    public function test_login_redirects_to_request_wizard_when_quick_request_draft_exists_for_family(): void
    {
        $user = User::factory()->create(['role' => 'family']);

        session([
            FamilyQuickRequestDraft::SESSION_KEY => [
                'recipient_full_name' => 'Margaret Johnson',
            ],
        ]);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('family.requests.create', absolute: false));
    }
}
