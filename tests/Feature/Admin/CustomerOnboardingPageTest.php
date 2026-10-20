<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CustomerOnboarding;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyPaymentSetupLink;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Auth\LoLoCareResetPasswordNotification;
use App\Services\CustomerOnboarding\FamilyPaymentSetupService;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerOnboardingPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.bypass' => true]);
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_open_directory_with_established_accounts_and_prospects(): void
    {
        $account = $this->account('Established Family');
        $lead = $this->lead('Prospective Family');

        $this->actingAs($this->admin)->get(route('admin.customer-onboarding.index'))
            ->assertOk()
            ->assertSee('Customer onboarding')
            ->assertSee($account->owner->name)
            ->assertSee($lead->name)
            ->assertSee('Request posted')
            ->assertSee('Caregiver hired');
        Livewire::actingAs($this->admin)->test(CustomerOnboarding::class)
            ->assertViewHas('families', fn ($families): bool => $families->total() === 2)
            ->set('search', 'Prospective')
            ->assertSee($lead->name)
            ->assertDontSee($account->owner->name)
            ->assertViewHas('families', fn ($families): bool => $families->total() === 1);
    }

    public function test_guest_is_sent_to_login_and_cannot_mount_livewire(): void
    {
        $this->get(route('admin.customer-onboarding.index'))->assertRedirect(route('login'));
        Livewire::test(CustomerOnboarding::class)->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return [['family'], ['sales'], ['sdr']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_cannot_open_page_or_mutate_an_existing_livewire_component(string $role): void
    {
        $account = $this->account('Protected Family');
        $other = User::factory()->create(['role' => $role]);
        $this->actingAs($other)->get(route('admin.customer-onboarding.index'))->assertForbidden();
        Livewire::actingAs($other)->test(CustomerOnboarding::class)->assertForbidden();

        $component = Livewire::actingAs($this->admin)->test(CustomerOnboarding::class)
            ->set('selectedKey', 'account:'.$account->id);
        $this->actingAs($other);
        $component->call('generatePaymentLink')->assertForbidden();
        $this->assertDatabaseCount('family_payment_setup_links', 0);
    }

    public function test_family_query_parameter_opens_detail_without_requesting_a_payment_refresh(): void
    {
        $account = $this->account('Directly Selected Family');
        $account->update(['stripe_customer_id' => 'cus_existing']);

        $this->actingAs($this->admin)
            ->get(route('admin.customer-onboarding.index', ['family' => 'account:'.$account->id]))
            ->assertOk()->assertSee('Directly Selected Family')->assertSee('Payment setup');
        Livewire::actingAs($this->admin)->withQueryParams(['family' => 'account:'.$account->id])
            ->test(CustomerOnboarding::class)
            ->assertSet('selectedKey', 'account:'.$account->id)
            ->assertViewHas('selected', fn ($selected): bool => $selected['account_id'] === $account->id)
            ->assertViewHas('payment', fn ($payment): bool => $payment['status'] === 'unknown');
        $this->assertNull($account->fresh()->payment_method_checked_at);
    }

    public function test_new_account_becomes_selected_and_access_email_requires_an_explicit_action(): void
    {
        $component = Livewire::actingAs($this->admin)->test(CustomerOnboarding::class)
            ->call('startCreate')
            ->set('name', 'Prepared Family')
            ->set('email', 'prepared@example.com')
            ->set('phone', '+19845550123')
            ->call('createFamily')
            ->assertHasNoErrors()
            ->assertSet('showCreate', false)
            ->assertSee('Family account ready');
        $owner = User::where('email', 'prepared@example.com')->sole();
        $account = FamilyAccount::where('owner_user_id', $owner->id)->sole();
        $component->assertSet('selectedKey', 'account:'.$account->id)->assertSee('Payment setup');
        $this->assertNull($owner->email_verified_at);
        Notification::assertNothingSent();
        Mail::assertNothingSent();

        $component->call('sendAccessEmail')->assertHasNoErrors()->assertSee('Account access email sent');
        Notification::assertSentToTimes($owner, LoLoCareResetPasswordNotification::class, 1);
        $this->assertDatabaseHas('family_account_activity_logs', [
            'family_account_id' => $account->id, 'actor_user_id' => $this->admin->id,
            'action' => 'customer_onboarding_access_email_sent',
        ]);
        $component->call('sendAccessEmail')->assertHasErrors(['access']);
        Notification::assertSentToTimes($owner, LoLoCareResetPasswordNotification::class, 1);
    }

    public function test_existing_lead_is_prefilled_and_converted_to_one_family_directory_record(): void
    {
        $lead = $this->lead('Onboarding Prospect', ['phone' => '+19845550987']);
        $component = Livewire::actingAs($this->admin)->test(CustomerOnboarding::class)
            ->call('openFamily', 'lead:'.$lead->id)
            ->call('startCreate')
            ->assertSet('creatingForLeadId', $lead->id)
            ->assertSet('name', $lead->name)
            ->assertSet('email', $lead->email)
            ->assertSet('phone', $lead->phone)
            ->call('createFamily')
            ->assertHasNoErrors();
        $accountId = $lead->fresh()->family_account_id;

        $this->assertNotNull($accountId);
        $component->assertSet('selectedKey', 'account:'.$accountId)
            ->call('closeFamily')
            ->assertViewHas('families', fn ($families): bool => $families->total() === 1 && $families->items()[0]['key'] === 'account:'.$accountId);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('family_accounts', 1);
        Notification::assertNothingSent();
    }

    public function test_conflicting_account_type_displays_email_error_and_keeps_creation_open(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver', 'email' => 'existing@example.com']);

        Livewire::actingAs($this->admin)->test(CustomerOnboarding::class)
            ->call('startCreate')
            ->set('name', 'Family Name')
            ->set('email', $caregiver->email)
            ->call('createFamily')
            ->assertHasErrors(['email'])
            ->assertSet('showCreate', true)
            ->assertSet('selectedKey', '');
        $this->assertDatabaseCount('family_accounts', 0);
        $this->assertSame('caregiver', $caregiver->fresh()->role);
        Notification::assertNothingSent();
    }

    public function test_admin_can_generate_view_and_revoke_a_shareable_payment_url(): void
    {
        $account = $this->account('Payment Family');
        $component = Livewire::actingAs($this->admin)->test(CustomerOnboarding::class)
            ->call('openFamily', 'account:'.$account->id)
            ->call('generatePaymentLink')
            ->assertHasNoErrors()
            ->assertSee('Copy link')
            ->assertSee('Show QR code');
        $link = FamilyPaymentSetupLink::where('family_account_id', $account->id)->sole();
        $url = app(FamilyPaymentSetupService::class)->urlFor($link);

        $component->assertViewHas('paymentLinkUrl', $url)->assertSeeHtml('value="'.e($url).'"');
        $this->assertTrue($link->isUsable());
        $this->assertNull($account->fresh()->stripe_customer_id);
        Notification::assertNothingSent();
        $component->call('revokePaymentLink')
            ->assertHasNoErrors()
            ->assertViewHas('paymentLinkUrl', null)
            ->assertDontSeeHtml('id="co-payment-link"');
        $this->assertSame('revoked', $link->fresh()->status());
    }

    public function test_closed_account_shows_history_and_rejects_payment_link_generation(): void
    {
        $account = $this->account('Closed Family');
        $account->update(['status' => FamilyAccount::STATUS_CLOSED]);

        Livewire::actingAs($this->admin)->test(CustomerOnboarding::class)
            ->call('openFamily', 'account:'.$account->id)
            ->assertSee('This family account is closed')
            ->assertDontSeeHtml('wire:click="generatePaymentLink"')
            ->assertDontSeeHtml('wire:click="sendAccessEmail"')
            ->call('generatePaymentLink')->assertForbidden();
        $this->assertDatabaseCount('family_payment_setup_links', 0);
    }

    public function test_refresh_does_not_report_a_bypass_customer_as_having_a_card(): void
    {
        $account = $this->account('Unchecked Family');
        $account->update(['stripe_customer_id' => 'cus_unchecked']);

        Livewire::actingAs($this->admin)->test(CustomerOnboarding::class)
            ->call('openFamily', 'account:'.$account->id)
            ->call('refreshPaymentStatus')
            ->assertHasNoErrors()
            ->assertViewHas('payment', fn ($payment): bool => $payment['status'] === 'unknown' && ! $payment['ready'])
            ->assertDontSee('Payment method ready');
        $this->assertNull($account->fresh()->payment_method_status);
        $this->assertNull($account->fresh()->payment_method_summary);
    }

    public function test_account_notes_are_audited_and_rendered_as_text(): void
    {
        $account = $this->account('Family With Notes');
        $note = '<script>alert("unsafe")</script> Meeting Tuesday.';

        Livewire::actingAs($this->admin)->test(CustomerOnboarding::class)
            ->call('openFamily', 'account:'.$account->id)
            ->set('note', $note)
            ->call('addNote')
            ->assertHasNoErrors()
            ->assertSet('note', '')
            ->assertSeeHtml(e($note))
            ->assertDontSeeHtml($note);
        $entry = FamilyAccountActivityLog::where('action', 'customer_onboarding_note')->sole();
        $this->assertSame($account->id, $entry->family_account_id);
        $this->assertSame($this->admin->id, $entry->actor_user_id);
        $this->assertSame($note, $entry->metadata['note']);
    }

    public function test_prospect_notes_are_preserved_on_the_crm_lead_and_escaped(): void
    {
        $lead = $this->lead('Prospect With Notes');
        $note = '<img src=x onerror=alert(1)> Bring the family information pack.';

        Livewire::actingAs($this->admin)->test(CustomerOnboarding::class)
            ->call('openFamily', 'lead:'.$lead->id)
            ->set('note', $note)
            ->call('addNote')
            ->assertHasNoErrors()
            ->assertSeeHtml(e($note))
            ->assertDontSeeHtml($note);
        $entry = $lead->activities()->sole();
        $this->assertSame($this->admin->id, $entry->actor_user_id);
        $this->assertSame($note, $entry->body);
        $this->assertDatabaseCount('family_accounts', 0);
    }

    private function account(string $name): FamilyAccount
    {
        return app(FamilyAccountProvisioner::class)->provisionOwner(User::factory()->create(['role' => 'family', 'name' => $name]));
    }

    private function lead(string $name, array $overrides = []): Lead
    {
        return Lead::withoutEvents(fn () => Lead::create([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => $name, 'email' => fake()->unique()->safeEmail(),
            'status' => 'new', 'source' => 'manual', ...$overrides,
        ]));
    }
}
