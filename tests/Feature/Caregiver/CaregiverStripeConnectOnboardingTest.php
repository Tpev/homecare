<?php

namespace Tests\Feature\Caregiver;

use App\Models\CaregiverProfile;
use App\Models\User;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverStripeConnectOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_caregiver_payout_page_links_back_to_the_dashboard(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'stripe_connect_account_id' => 'acct_incomplete_active',
        ]);

        $this->actingAs($caregiver)
            ->get(route('caregiver.payouts.connect.show'))
            ->assertOk()
            ->assertSee('Back to dashboard')
            ->assertDontSee('Back to setup')
            ->assertSee('href="'.route('dashboard').'"', false);
    }

    public function test_interrupted_onboarding_refresh_creates_a_fresh_account_link(): void
    {
        config()->set('services.stripe.bypass', false);

        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'draft',
            'stripe_connect_account_id' => 'acct_resume_onboarding',
        ]);

        $stripe = new class extends StripeClient
        {
            /** @var list<array{account_id:string,refresh_url:string,return_url:string}> */
            public array $linkRequests = [];

            public function ensureCaregiverConnectAccount(CaregiverProfile $profile): string
            {
                return (string) $profile->stripe_connect_account_id;
            }

            public function createConnectOnboardingLink(
                string $accountId,
                string $refreshUrl,
                string $returnUrl,
            ): string {
                $this->linkRequests[] = [
                    'account_id' => $accountId,
                    'refresh_url' => $refreshUrl,
                    'return_url' => $returnUrl,
                ];

                return 'https://connect.stripe.test/onboarding/'.count($this->linkRequests);
            }
        };
        app()->instance(StripeClient::class, $stripe);

        $this->actingAs($caregiver)
            ->post(route('caregiver.payouts.connect.start'))
            ->assertRedirect('https://connect.stripe.test/onboarding/1');

        $this->actingAs($caregiver)
            ->get(route('caregiver.payouts.connect.refresh'))
            ->assertRedirect('https://connect.stripe.test/onboarding/2');

        $this->assertSame([
            [
                'account_id' => 'acct_resume_onboarding',
                'refresh_url' => route('caregiver.payouts.connect.refresh'),
                'return_url' => route('caregiver.payouts.connect.return'),
            ],
            [
                'account_id' => 'acct_resume_onboarding',
                'refresh_url' => route('caregiver.payouts.connect.refresh'),
                'return_url' => route('caregiver.payouts.connect.return'),
            ],
        ], $stripe->linkRequests);
    }
}
