<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_and_family_routes_are_publicly_accessible(): void
    {
        $this->get('/')->assertOk();
        $this->get('/families')->assertOk();
    }

    public function test_agency_page_is_not_exposed(): void
    {
        $this->get('/agencies')->assertNotFound();
    }

    public function test_legal_index_and_pages_are_publicly_accessible(): void
    {
        $this->get(route('legal.index'))
            ->assertOk()
            ->assertSee('Legal Documents');

        foreach (array_keys(config('legal_pages.pages', [])) as $slug) {
            $this->get(route('legal.show', ['slug' => $slug]))
                ->assertOk();
        }
    }

    public function test_public_privacy_policy_uses_lolo_company_and_sms_consent_language(): void
    {
        $this->get(route('legal.show', ['slug' => 'privacy-policy']))
            ->assertOk()
            ->assertSeeText('LoLo Care Inc')
            ->assertSeeText('Privacy Policy')
            ->assertSeeText('mobile information')
            ->assertSeeText('SMS consent')
            ->assertSeeText('third parties or affiliates')
            ->assertSeeText('marketing or promotional purposes')
            ->assertDontSeeText('HUB Healthcare')
            ->assertDontSeeText('HomeCare');
    }

    public function test_sms_opt_in_evidence_page_is_publicly_accessible(): void
    {
        $this->get(route('legal.sms-opt-in-evidence'))
            ->assertOk()
            ->assertSeeText('Optional SMS opt-in appears on Step 7 of the public get-care form.')
            ->assertSee(route('landing.get-care'), false)
            ->assertSeeText('Optional: I agree LoLo may text me about this care request.')
            ->assertSeeText('Message and data rates may apply.')
            ->assertSeeText('I can reply STOP to texts.')
            ->assertSeeText('Leave this unchecked if you prefer only a phone callback.')
            ->assertSeeText('The SMS checkbox is optional and is not required to request a callback.')
            ->assertDontSeeText('The checkbox is required before the callback request can be submitted.')
            ->assertDontSeeText('Required SMS/call consent checkbox')
            ->assertSee(route('legal.show', ['slug' => 'privacy-policy']), false);
    }

    public function test_registration_pages_show_terms_links(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee(route('legal.show', ['slug' => 'platform-terms-of-service']), false)
            ->assertSee(route('legal.show', ['slug' => 'privacy-policy']), false);

        $this->get('/caregiver/register')
            ->assertOk()
            ->assertSee(route('legal.show', ['slug' => 'platform-terms-of-service']), false)
            ->assertSee(route('legal.show', ['slug' => 'caregiver-terms']), false)
            ->assertSee(route('legal.show', ['slug' => 'platform-participation-acknowledgment']), false);
    }
}
