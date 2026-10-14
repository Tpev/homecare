<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_and_family_routes_are_publicly_accessible(): void
    {
        $this->get('/')->assertOk();
        $this->get('/families')->assertStatus(301)->assertRedirect(route('landing'));
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
            $response = $this->get(route('legal.show', ['slug' => $slug]))
                ->assertOk()
                ->assertSeeText('Effective Date: August 1, 2026');
            $response
                ->assertSeeText(match ($slug) {
                    'privacy-policy', 'platform-terms-of-service', 'caregiver-terms' => 'Last Updated: October 14, 2026',
                    'payment-terms-and-cancellation-policy' => 'Last Updated: August 26, 2026',
                    default => 'Last Updated: August 1, 2026',
                })
                ->assertDontSeeText('TO BE REMOVED')
                ->assertDontSeeText('State-Specific and Operational Placeholders for Counsel Review')
                ->assertDontSeeText('intended as a business draft for review and customization')
                ->assertDontSeeText('This does not seem accurate')
                ->assertDontSeeText('please explain');
        }

        $this->get('/legal/enterprise-and-partner-terms')->assertNotFound();
    }

    public function test_public_privacy_policy_uses_lolo_company_and_sms_consent_language(): void
    {
        $this->get(route('legal.show', ['slug' => 'privacy-policy']))
            ->assertOk()
            ->assertSeeText('LoLo Care, Inc.')
            ->assertSeeText('Privacy Policy')
            ->assertSeeText('mobile information')
            ->assertSeeText('SMS consent')
            ->assertSeeText('third parties or affiliates')
            ->assertSeeText('marketing or promotional purposes')
            ->assertSeeText('SMS Consent & Mobile Data Non-Sharing')
            ->assertSeeText('No mobile information will be shared with third parties or affiliates for marketing or promotional purposes.')
            ->assertDontSeeText('HUB Healthcare')
            ->assertDontSeeText('HomeCare');
    }

    public function test_platform_terms_explain_explicit_sms_consent_and_messaging_controls(): void
    {
        $this->get(route('legal.show', ['slug' => 'platform-terms-of-service']))
            ->assertOk()
            ->assertSeeText('SMS / Text Messaging Terms and Conditions')
            ->assertSeeText('provide your mobile phone number, and check the optional SMS consent box')
            ->assertSeeText('Consent is not required to create an account or submit the registration form.')
            ->assertSeeText('Reply STOP')
            ->assertSeeText('Reply HELP')
            ->assertSeeText('hello@carelolo.com')
            ->assertSeeText('Message and data rates may apply.')
            ->assertSeeText('Wireless carriers are not liable for delayed or undelivered messages.');
    }

    public static function registrationPages(): array
    {
        return [
            'family' => ['/register'],
            'caregiver' => ['/caregiver/register'],
        ];
    }

    #[DataProvider('registrationPages')]
    public function test_registration_sms_consent_is_optional_unchecked_and_between_terms_and_submit_button(string $path): void
    {
        $response = $this->get($path)->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $checkboxes = $xpath->query('//form//input[@id="sms_opt_in" and @type="checkbox"]');

        $this->assertCount(1, $checkboxes);
        $checkbox = $checkboxes->item(0);
        $this->assertSame('sms_opt_in', $checkbox->getAttribute('wire:model'));
        $this->assertFalse($checkbox->hasAttribute('checked'));
        $this->assertFalse($checkbox->hasAttribute('required'));
        $this->assertCount(1, $xpath->query('//form//input[@*[name()="wire:model"]="accept_terms"]/following::input[@id="sms_opt_in"]'));
        $this->assertCount(1, $xpath->query('//form//button[@type="submit"]/preceding::input[@id="sms_opt_in"]'));

        $phones = $xpath->query('//form//input[@type="tel" and @*[name()="wire:model"]="phone"]');
        $this->assertCount(1, $phones);
        $this->assertFalse($phones->item(0)->hasAttribute('required'));

        $labels = $xpath->query('//label[@for="sms_opt_in"]');
        $this->assertCount(1, $labels);
        $this->assertSame(
            'By providing your phone number and checking this box, you consent to receive SMS messages from Lolo Care,Inc. regarding appointment reminders, service updates, and customer support. Message frequency may vary. Standard message and data rates may apply. Text STOP to unsubscribe or HELP for assistance. See our Privacy Policy and Terms of Service. Consent is not required to submit this form.',
            preg_replace('/\s+/', ' ', trim($labels->item(0)->textContent)),
        );
        foreach (['privacy-policy', 'platform-terms-of-service'] as $slug) {
            $this->assertCount(1, $xpath->query('//label[@for="sms_opt_in"]//a[@href="'.route('legal.show', ['slug' => $slug]).'"]'));
        }
    }

    public function test_legal_documents_render_counsel_lists_semantically(): void
    {
        $this->get(route('legal.show', ['slug' => 'acceptable-use-and-safety-policy']))
            ->assertOk()
            ->assertSee('<h2', false)
            ->assertSee('<ul', false)
            ->assertSee('<li>harassment, threats, intimidation, or discrimination</li>', false);
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
