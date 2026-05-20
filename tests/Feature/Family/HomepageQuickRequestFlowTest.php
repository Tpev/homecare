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
            ->assertSeeText('Request a callback')
            ->assertSeeText('Tell us what kind of support you need.');

        Livewire::test(CallbackRequest::class)
            ->set('full_name', 'Jane Family')
            ->set('phone', '(984) 400-0000')
            ->set('email', 'jane@example.com')
            ->set('zip', '27601')
            ->set('service_type', 'Companion care')
            ->set('callback_time', 'tomorrow_morning')
            ->set('notes', 'My mom needs companionship twice a week.')
            ->call('submit')
            ->assertSet('submitted', true);

        $lead = Lead::query()->sole();

        $this->assertSame('family', $lead->lead_type);
        $this->assertSame('Jane Family', $lead->name);
        $this->assertSame('(984) 400-0000', $lead->phone);
        $this->assertSame('27601', $lead->zip);
        $this->assertSame('callback_request', $lead->data['intent']);
        $this->assertSame('Companion care', $lead->data['service_type']);
        $this->assertSame('Tomorrow morning', $lead->data['callback_time_label']);

        Mail::assertSent(CallbackRequestOpsAlertMail::class, function (CallbackRequestOpsAlertMail $mail) use ($lead) {
            return $mail->lead->is($lead)
                && $mail->hasTo('peverelli.t@gmail.com')
                && $mail->hasTo('cpetrinipoli@hub.healthcare')
                && str_contains($mail->render(), 'My mom needs companionship twice a week.');
        });
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
