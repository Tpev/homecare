<?php

namespace Tests\Feature\Family;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\FamilyBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HirePaymentSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.bypass' => false, 'marketplace.prelaunch_enabled' => false]);
    }

    public static function requestTypes(): array
    {
        return [
            'one-time applicant' => [CareRequest::TYPE_ONE_TIME, false],
            'one-time invitation' => [CareRequest::TYPE_ONE_TIME, true],
            'recurring applicant' => [CareRequest::TYPE_RECURRING, false],
            'recurring invitation' => [CareRequest::TYPE_RECURRING, true],
        ];
    }

    #[DataProvider('requestTypes')]
    public function test_missing_card_disables_hire_but_keeps_review_and_chat_available(string $type, bool $invited): void
    {
        [$family, $request, $application] = $this->fixture($type, $invited);
        $this->mock(FamilyBillingService::class)->shouldReceive('summaryFor')->andReturn(['ready' => false]);
        $this->mock(BookingPaymentService::class)->shouldNotReceive('prepareOnSessionAuthorization');

        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')
            ->assertSee('Add a card to hire')->assertSee('Add card securely')
            ->assertSee('Review care & price')->assertSee($invited ? 'Start chat' : 'Shortlist & chat');
        $this->assertButtonDisabled($component->html(), 'reviewHire('.$application->id.')', true);

        $component->call('reviewHire', $application->id)
            ->assertSee($type === CareRequest::TYPE_RECURRING ? 'First visit estimate' : 'Visit estimate')->assertSee('No charge now.')
            ->call('confirmReviewedHire')->assertSet('reviewingApplicationId', $application->id);
        $this->assertButtonDisabled($component->html(), 'confirmReviewedHire', true);
        $this->assertUnhired($request, $application);
    }

    public function test_payment_readiness_is_refreshed_and_enables_hiring_after_card_setup(): void
    {
        [$family, $request, $application] = $this->fixture();
        $ready = false;
        $this->mock(FamilyBillingService::class)->shouldReceive('summaryFor')
            ->andReturnUsing(function () use (&$ready): array {
                return ['ready' => $ready];
            });
        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')->call('reviewHire', $application->id);
        $this->assertButtonDisabled($component->html(), 'confirmReviewedHire', true);

        $ready = true;
        $component->call('$refresh')->assertDontSee('Add a card to hire');
        $this->assertButtonDisabled($component->html(), 'confirmReviewedHire', false);
        $component->call('closeDecisionReview');
        $this->assertButtonDisabled($component->html(), 'reviewHire('.$application->id.')', false);
        $this->assertUnhired($request, $application);
    }

    public function test_failed_payment_lookup_offers_a_retry_without_claiming_no_card_is_saved(): void
    {
        [$family, $request, $application] = $this->fixture();
        $this->mock(FamilyBillingService::class)->shouldReceive('summaryFor')
            ->andThrow(new PaymentException('Unable to load billing method right now.'));
        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')->assertSee('Check again')
            ->assertSee('Update card securely')->assertDontSee('Add a card to hire');
        $this->assertButtonDisabled($component->html(), 'reviewHire('.$application->id.')', true);
    }

    public static function familyActors(): array
    {
        return ['owner' => [false], 'member' => [true]];
    }

    #[DataProvider('familyActors')]
    public function test_successful_setup_returns_to_the_same_hire_review_without_hiring(bool $asMember): void
    {
        [$family, $request, $application] = $this->fixture();
        if ($asMember) {
            $account = app(FamilyAccountContext::class)->account($family);
            $family = User::factory()->create(['role' => 'family']);
            $account->memberships()->create([
                'user_id' => $family->id, 'access_level' => FamilyAccountMember::ACCESS_MEMBER,
                'status' => FamilyAccountMember::STATUS_ACTIVE, 'joined_at' => now(),
            ]);
        }
        $context = ['care_request_id' => $request->id, 'application_id' => $application->id];
        $successUrl = '';
        $billing = $this->mock(FamilyBillingService::class);
        $billing->shouldReceive('createSetupCheckoutUrl')->once()
            ->withArgs(function (User $actor, string $success, string $cancel) use ($family, $context, &$successUrl): bool {
                $successUrl = str_replace('{CHECKOUT_SESSION_ID}', 'cs_test_hire', $success);
                parse_str(parse_url($success, PHP_URL_QUERY), $successQuery);
                parse_str(parse_url($cancel, PHP_URL_QUERY), $cancelQuery);
                $this->assertSame((string) $context['care_request_id'], $successQuery['care_request_id']);
                $this->assertSame((string) $context['application_id'], $successQuery['application_id']);
                $this->assertSame('cancel', $cancelQuery['checkout']);
                $this->assertSame($successQuery['application_id'], $cancelQuery['application_id']);

                return $actor->is($family);
            })->andReturn('https://checkout.stripe.com/c/pay/cs_test_hire');
        $billing->shouldReceive('syncSetupCheckoutSession')->once()->withArgs(fn (User $actor, string $id): bool => $actor->is($family) && $id === 'cs_test_hire');
        $billing->shouldReceive('summaryFor')->andReturn(['ready' => true]);

        $this->actingAs($family)->post(route('family.billing.checkout'), $context)
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_hire');
        $this->get($successUrl)->assertRedirect($this->reviewUrl($request, $application))
            ->assertSessionHas('status', 'Payment method saved. Review the details below to confirm your hire.');
        $response = $this->get($this->reviewUrl($request, $application))->assertOk()->assertSee('Care with Casey Morgan');
        $this->assertButtonDisabled($response->getContent(), 'confirmReviewedHire', false);
        $this->assertUnhired($request, $application);
    }

    public static function checkoutOutcomes(): array
    {
        return ['cancelled' => ['cancel'], 'failed to start' => ['start'], 'verification failed' => ['verify']];
    }

    #[DataProvider('checkoutOutcomes')]
    public function test_cancelled_or_failed_setup_returns_to_the_selected_caregiver(string $outcome): void
    {
        [$family, $request, $application] = $this->fixture();
        $billing = $this->mock(FamilyBillingService::class);
        $billing->shouldReceive('summaryFor')->andReturn(['ready' => false]);
        $context = ['care_request_id' => $request->id, 'application_id' => $application->id];
        $this->actingAs($family);
        if ($outcome === 'start') {
            $billing->shouldReceive('createSetupCheckoutUrl')->once()->andThrow(new PaymentException('Please try card setup again.'));
            $response = $this->post(route('family.billing.checkout'), $context);
        } elseif ($outcome === 'verify') {
            $billing->shouldReceive('syncSetupCheckoutSession')->once()->andThrow(new PaymentException('Card setup is not completed yet.'));
            $response = $this->get(route('family.billing.show', [...$context, 'checkout_session_id' => 'cs_incomplete']));
        } else {
            $billing->shouldNotReceive('syncSetupCheckoutSession');
            $response = $this->get(route('family.billing.show', [...$context, 'checkout' => 'cancel']));
        }
        $response->assertRedirect($this->reviewUrl($request, $application));
        $page = $this->get($this->reviewUrl($request, $application))->assertOk()->assertSee('Care with Casey Morgan');
        if ($outcome !== 'cancel') {
            $page->assertSee($outcome === 'start' ? 'Please try card setup again.' : 'Card setup is not completed yet.');
        }
        $this->assertButtonDisabled($page->getContent(), 'confirmReviewedHire', true);
        $this->assertUnhired($request, $application);
    }

    public function test_checkout_context_rejects_other_families_and_mismatched_applications(): void
    {
        [$family, $request, $application] = $this->fixture();
        [$otherFamily, $otherRequest, $otherApplication] = $this->fixture();
        $this->mock(FamilyBillingService::class)->shouldNotReceive('createSetupCheckoutUrl');
        $this->actingAs($otherFamily)->post(route('family.billing.checkout'), [
            'care_request_id' => $request->id, 'application_id' => $application->id,
        ])->assertNotFound();
        $this->actingAs($family)->post(route('family.billing.checkout'), [
            'care_request_id' => $request->id, 'application_id' => $otherApplication->id,
        ])->assertNotFound();
        $this->get(route('family.billing.show', [
            'care_request_id' => $otherRequest->id, 'application_id' => $otherApplication->id, 'checkout' => 'cancel',
        ]))->assertNotFound();
    }

    public function test_a_closed_request_does_not_reopen_hire_review_after_returning(): void
    {
        [$family, $request, $application] = $this->fixture();
        $request->update(['status' => CareRequest::STATUS_CANCELLED]);
        Livewire::actingAs($family)->withQueryParams(['review_hire' => $application->id])
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('reviewingApplicationId', null)->assertDontSee('Confirm hire');
    }

    private function assertButtonDisabled(string $html, string $action, bool $disabled): void
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $buttons = (new \DOMXPath($document))->query('//button[@*[name()="wire:click"]="'.$action.'"][not(contains(@class, "hc-care-text-link"))]');
        $this->assertCount(1, $buttons);
        $this->assertSame($disabled, $buttons->item(0)->hasAttribute('disabled'));
    }

    private function assertUnhired(CareRequest $request, CareRequestApplication $application): void
    {
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
        $this->assertNotSame(CareRequestApplication::STATUS_HIRED, $application->fresh()->status);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_plans', 0);
    }

    private function reviewUrl(CareRequest $request, CareRequestApplication $application): string
    {
        return route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'applicants', 'review_hire' => $application->id]);
    }

    private function fixture(string $type = CareRequest::TYPE_ONE_TIME, bool $invited = false): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Casey Morgan']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active', 'years_experience' => 6]);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id, 'title' => 'Care for Ellie', 'request_type' => $type,
            'status' => CareRequest::STATUS_OPEN, 'is_private' => $invited,
            'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'address_line1' => '123 Local Demo Lane',
            'scope_of_work' => 'Companionship and meals',
            'requested_start_at' => now()->addDays(2)->setTime(9, 0), 'requested_end_at' => now()->addDays(2)->setTime(11, 0),
            'recurring_schedule' => [['day' => 1, 'start_time' => '09:00', 'end_time' => '11:00']],
            'recurring_starts_on' => now()->addDays(2)->toDateString(),
        ]);
        $request->recipient()->create(['full_name' => 'Ellie', 'relationship_to_family' => 'Mother']);
        $application = $request->applications()->create([
            'caregiver_user_id' => $caregiver->id,
            'status' => $invited ? CareRequestApplication::STATUS_SHORTLISTED : CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30, 'cover_note' => 'Happy to help with companionship and meals.',
        ]);

        return [$family, $request, $application];
    }
}
