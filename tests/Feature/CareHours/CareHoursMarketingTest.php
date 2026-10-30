<?php

namespace Tests\Feature\CareHours;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CareHoursMarketingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'care_hours.enabled' => true, 'care_hours.gifts_enabled' => true,
            'care_hours.sales_enabled' => true, 'care_hours.welcome_enabled' => true,
            'care_hours.pilot_only' => true, 'care_hours.pilot_emails' => ['peverelli.t@gmail.com'],
        ]);
        Notification::fake();
    }

    public function test_public_pages_have_distinct_metadata_clean_canonicals_and_visible_structured_faqs(): void
    {
        $titles = [];
        foreach (['prepaid-care-hours', 'care-gift-cards'] as $name) {
            $url = route($name);
            $response = $this->get($url.'?utm_source=test&code=not-a-real-gift')->assertOk();
            $dom = $this->dom($response);
            $title = $dom->evaluate('string(//title)');
            $this->assertNotContains($title, $titles);
            $titles[] = $title;
            $this->assertSame(1, $dom->query('//h1')->length);
            $this->assertSame($url, $dom->evaluate('string(//link[@rel="canonical"]/@href)'));
            $this->assertSame($url, $dom->evaluate('string(//meta[@property="og:url"]/@content)'));
            $this->assertStringContainsString('index,follow', $dom->evaluate('string(//meta[@name="robots"]/@content)'));
            $this->assertNotEmpty($dom->evaluate('string(//meta[@name="description"]/@content)'));
            $this->assertNotEmpty($dom->evaluate('string(//meta[@property="og:image"]/@content)'));
            $script = $dom->query('//script[@type="application/ld+json"]')->item(0);
            $this->assertNotNull($script);
            $schema = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('https://schema.org', $schema['@context']);
            $nodes = collect($schema['@graph'])->keyBy('@type');
            $this->assertSame($url, $nodes['WebPage']['url']);
            $this->assertSame($title, $nodes['WebPage']['name']);
            $this->assertSame($url, $nodes['BreadcrumbList']['itemListElement'][1]['item']);
            foreach ($response->viewData('faqs') as $index => $faq) {
                $this->assertSame($faq['question'], $nodes['FAQPage']['mainEntity'][$index]['name']);
                $this->assertSame($faq['answer'], $nodes['FAQPage']['mainEntity'][$index]['acceptedAnswer']['text']);
                $response->assertSee($faq['question'])->assertSee($faq['answer']);
            }
            $response->assertDontSee('not-a-real-gift');
        }
        $this->assertNoFinancialChanges();
    }

    public function test_guest_and_other_family_can_read_pages_without_obtaining_pilot_purchase_access(): void
    {
        foreach ([null, User::factory()->create(['role' => 'family', 'email' => 'jane@example.test'])] as $user) {
            if ($user) {
                $this->actingAs($user);
            }
            foreach (['prepaid-care-hours', 'care-gift-cards'] as $name) {
                $this->get(route($name))->assertOk()->assertViewHas('canPurchase', false)
                    ->assertViewHas('purchaseUrl', route('landing.get-care'))
                    ->assertSee('Ask about availability');
            }
            $this->get(route('care-hours.gifts.index'))->assertNotFound();
        }
        $this->assertTrue(config('care_hours.pilot_only'));
        $this->assertSame(['peverelli.t@gmail.com'], config('care_hours.pilot_emails'));
        $this->assertNoFinancialChanges();
    }

    public function test_only_allowed_buyer_receives_existing_purchase_links_and_pauses_still_apply(): void
    {
        $owner = User::factory()->create(['role' => 'family', 'email' => 'peverelli.t@gmail.com']);
        $this->actingAs($owner)->get(route('prepaid-care-hours'))->assertOk()
            ->assertViewHas('canPurchase', true)->assertViewHas('purchaseUrl', route('family.care-hours.buy'));
        $this->get(route('care-gift-cards'))->assertOk()
            ->assertViewHas('canPurchase', true)->assertViewHas('purchaseUrl', route('care-hours.gifts.index'));
        config(['care_hours.sales_enabled' => false]);
        foreach (['prepaid-care-hours', 'care-gift-cards'] as $name) {
            $this->get(route($name))->assertOk()->assertViewHas('canPurchase', false)
                ->assertViewHas('purchaseUrl', route('landing.get-care'));
        }
        $this->get(route('care-gift-cards'))->assertSee(route('care-hours.claim'), false);
        $this->assertNoFinancialChanges();
    }

    public function test_package_prices_and_conditional_welcome_hours_come_from_the_existing_catalog(): void
    {
        $packages = $this->get(route('prepaid-care-hours'))->assertOk()->viewData('packages');
        $this->assertEquals([5, 10, 20, 40], array_column($packages, 'hours'));
        $this->assertSame([15500, 31000, 62000, 124000], array_column($packages, 'amount_cents'));
        $this->assertEquals([0, 1, 2, 4], array_column($packages, 'bonus_hours'));
        $this->assertSame(['$155', '$310', '$620', '$1,240'], array_column($packages, 'formatted_price'));
        config(['care_hours.welcome_enabled' => false]);
        foreach (['prepaid-care-hours', 'care-gift-cards'] as $name) {
            $response = $this->get(route($name))->assertOk();
            $this->assertEquals([0, 0, 0, 0], array_column($response->viewData('packages'), 'bonus_hours'));
            $this->assertEquals([5, 10, 20, 40], array_column($response->viewData('packages'), 'total_hours'));
            $response->assertSee('Welcome bonuses are not currently available.');
        }
        $this->assertNoFinancialChanges();
    }

    public function test_marketing_pages_are_in_sitemap_but_private_checkout_is_not(): void
    {
        $this->get(route('sitemap.xml'))->assertOk()
            ->assertSee(route('prepaid-care-hours'), false)->assertSee(route('care-gift-cards'), false)
            ->assertDontSee(route('care-hours.gifts.index'), false)
            ->assertDontSee(route('family.care-hours.buy'), false);
    }

    private function dom(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function assertNoFinancialChanges(): void
    {
        foreach (['care_hour_accounts', 'care_hour_purchases', 'care_hour_lots', 'care_hour_allocations', 'care_booking_payments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Notification::assertNothingSent();
    }
}
