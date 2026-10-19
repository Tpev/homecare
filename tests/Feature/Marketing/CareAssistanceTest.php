<?php

namespace Tests\Feature\Marketing;

use App\Models\CareAssistanceApplication;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CareAssistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_has_canonical_metadata_matching_faq_schema_and_no_marketing_trackers(): void
    {
        $response = $this->get(route('care-assistance.index').'?utm_source=community')->assertOk();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $dom = new DOMXPath($document);

        $this->assertSame(1, $dom->query('//h1')->length);
        $this->assertSame(1, $dom->query('//title')->length);
        $this->assertSame(1, $dom->query('//link[@rel="canonical"]')->length);
        $this->assertSame(1, $dom->query('//meta[@name="description"]')->length);
        $this->assertSame(1, $dom->query('//meta[@name="robots"]')->length);
        $this->assertSame('index,follow,max-image-preview:large', $dom->evaluate('string(//meta[@name="robots"]/@content)'));
        $response->assertHeaderMissing('X-Robots-Tag');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('$23', $dom->evaluate('string(//title)'));
        $this->assertSame(route('care-assistance.index'), $dom->evaluate('string(//link[@rel="canonical"]/@href)'));
        $this->assertSame(route('care-assistance.index'), $dom->evaluate('string(//meta[@property="og:url"]/@content)'));
        $description = $dom->evaluate('string(//meta[@name="description"]/@content)');
        $this->assertNotEmpty($description);
        $this->assertSame($description, $dom->evaluate('string(//meta[@property="og:description"]/@content)'));
        $this->assertSame($description, $dom->evaluate('string(//meta[@name="twitter:description"]/@content)'));
        foreach (['og:title', 'twitter:title'] as $name) {
            $this->assertSame($dom->evaluate('string(//title)'), $dom->evaluate('string(//meta[@property="'.$name.'" or @name="'.$name.'"]/@content)'));
        }
        $response->assertSee('five business days')->assertDontSee('fbevents.js')->assertDontSee('googletagmanager.com');

        $nodes = [];
        foreach ($dom->query('//script[@type="application/ld+json"]') as $script) {
            $schema = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('https://schema.org', $schema['@context']);
            $nodes = array_merge($nodes, $schema['@graph'] ?? [$schema]);
        }
        $faq = collect($nodes)->first(fn ($node) => in_array('FAQPage', (array) ($node['@type'] ?? []), true));
        $this->assertNotNull($faq);
        $this->assertCount(count(config('care_assistance.faqs')), $faq['mainEntity']);
        foreach ($faq['mainEntity'] as $question) {
            $this->assertStringContainsString($question['name'], $document->textContent);
            $this->assertStringContainsString($question['acceptedAnswer']['text'], $document->textContent);
        }

        $webpage = collect($nodes)->firstWhere('@type', 'WebPage');
        $service = collect($nodes)->firstWhere('@type', 'Service');
        $image = collect($nodes)->firstWhere('@type', 'ImageObject');
        $breadcrumb = collect($nodes)->firstWhere('@type', 'BreadcrumbList');
        $this->assertSame($description, $webpage['description']);
        $this->assertSame(route('care-assistance.index'), $webpage['url']);
        $this->assertSame($image['@id'], $webpage['primaryImageOfPage']['@id']);
        $this->assertSame($image['@id'], $service['image']['@id']);
        $this->assertSame($webpage['@id'], $service['mainEntityOfPage']['@id']);
        $this->assertSame('23.00', $service['offers']['priceSpecification']['price']);
        $this->assertSame('USD', $service['offers']['priceSpecification']['priceCurrency']);
        $this->assertSame('hour', $service['offers']['priceSpecification']['unitText']);
        $this->assertStringContainsString('approved', $service['offers']['description']);
        foreach ($service['areaServed'] as $area) {
            $this->assertStringContainsString($area['name'], $description);
            $this->assertStringContainsString($area['name'], $document->textContent);
        }
        $this->assertSame(route('care-assistance.index'), $breadcrumb['itemListElement'][1]['item']);

        $socialUrl = $dom->evaluate('string(//meta[@property="og:image"]/@content)');
        $this->assertSame($socialUrl, $dom->evaluate('string(//meta[@name="twitter:image"]/@content)'));
        $this->assertSame($socialUrl, $image['contentUrl']);
        $socialPath = public_path(ltrim(parse_url($socialUrl, PHP_URL_PATH), '/'));
        $this->assertFileExists($socialPath);
        $dimensions = getimagesize($socialPath);
        $this->assertSame((string) $dimensions[0], $dom->evaluate('string(//meta[@property="og:image:width"]/@content)'));
        $this->assertSame((string) $dimensions[1], $dom->evaluate('string(//meta[@property="og:image:height"]/@content)'));
        $this->assertSame($dimensions['mime'], $dom->evaluate('string(//meta[@property="og:image:type"]/@content)'));
        $this->assertSame([$dimensions[0], $dimensions[1]], [$image['width'], $image['height']]);
        $this->assertSame($dom->evaluate('string(//meta[@property="og:image:alt"]/@content)'), $image['caption']);
        $this->assertSame('summary_large_image', $dom->evaluate('string(//meta[@name="twitter:card"]/@content)'));

        $hero = $dom->query('//img[@class="hero-photo"]')->item(0);
        $preload = $dom->query('//link[@rel="preload" and @as="image"]')->item(0);
        $this->assertNotNull($hero);
        $this->assertNotNull($preload);
        $this->assertSame('high', $hero->getAttribute('fetchpriority'));
        $this->assertNotSame('lazy', $hero->getAttribute('loading'));
        $this->assertSame($hero->getAttribute('srcset'), $preload->getAttribute('imagesrcset'));
        $this->assertSame($hero->getAttribute('sizes'), $preload->getAttribute('imagesizes'));
        foreach ($dom->query('//main//img') as $photo) {
            $this->assertNotEmpty($photo->getAttribute('alt'));
            $this->assertGreaterThan(0, (int) $photo->getAttribute('width'));
            $this->assertGreaterThan(0, (int) $photo->getAttribute('height'));
            foreach (explode(',', $photo->getAttribute('srcset')) as $candidate) {
                [$url, $width] = preg_split('/\s+/', trim($candidate));
                $path = public_path(ltrim(parse_url($url, PHP_URL_PATH), '/'));
                $this->assertFileExists($path);
                $this->assertSame((int) $width, getimagesize($path)[0]);
            }
        }
    }

    public function test_guest_can_apply_without_creating_an_account_and_cannot_set_review_fields(): void
    {
        Mail::fake();
        Notification::fake();
        $response = $this->post(route('care-assistance.store'), array_merge($this->payload(), [
            'status' => 'approved', 'internal_notes' => 'Forged decision', 'reviewed_by' => 99, 'user_id' => 99,
        ]));

        $response->assertRedirect(route('care-assistance.index').'#apply')
            ->assertSessionHas('care_assistance_submitted', true)->assertSessionHasNoErrors();
        $application = CareAssistanceApplication::query()->sole();
        $this->assertSame('new', $application->status);
        $this->assertNull($application->user_id);
        $this->assertNull($application->internal_notes);
        $this->assertNull($application->reviewed_by);
        $this->assertNotNull($application->consented_at);
        $this->assertSame(['companionship', 'meal_preparation'], $application->care_needs);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('care_pricing_agreements', 0);
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();

        $confirmation = $this->get(route('care-assistance.index'))->assertOk();
        $confirmation->assertSee('five business days')->assertDontSee('name="hardship_details"', false)
            ->assertDontSee($this->payload()['hardship_details']);
    }

    public function test_authenticated_applicant_is_linked_to_their_own_account(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $this->actingAs($user)->post(route('care-assistance.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame($user->id, CareAssistanceApplication::query()->sole()->user_id);
    }

    public function test_required_details_and_consent_are_validated(): void
    {
        $this->from(route('care-assistance.index'))->post(route('care-assistance.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'relationship', 'city', 'zip', 'care_needs', 'weekly_hours', 'start_timeframe', 'hardship_details', 'consent']);
        $this->assertDatabaseCount('care_assistance_applications', 0);
    }

    public function test_invalid_choices_contact_details_and_oversized_explanations_are_rejected(): void
    {
        $this->post(route('care-assistance.store'), array_merge($this->payload(), [
            'email' => 'not-an-email', 'phone' => 'abc', 'zip' => '123', 'relationship' => 'unknown',
            'care_needs' => ['invalid'], 'weekly_hours' => '-10', 'start_timeframe' => 'yesterday',
            'hardship_details' => str_repeat('a', 3001), 'consent' => '0',
        ]))->assertSessionHasErrors(['email', 'phone', 'zip', 'relationship', 'care_needs.0', 'weekly_hours', 'start_timeframe', 'hardship_details', 'consent']);
        $this->assertDatabaseCount('care_assistance_applications', 0);
    }

    public function test_honeypot_rejects_automated_submissions(): void
    {
        $this->post(route('care-assistance.store'), array_merge($this->payload(), ['website' => 'https://spam.example']))
            ->assertSessionHasErrors('website');
        $this->assertDatabaseCount('care_assistance_applications', 0);
    }

    public function test_malformed_field_shapes_return_a_usable_validation_page(): void
    {
        $payload = array_fill_keys(['name', 'email', 'phone', 'relationship', 'city', 'zip', 'weekly_hours', 'start_timeframe', 'hardship_details', 'consent'], ['unexpected']);
        $payload['care_needs'] = 'invalid';
        $this->post(route('care-assistance.store'), $payload)
            ->assertRedirect(route('care-assistance.index').'#apply')->assertSessionHasErrors();
        $response = $this->get(route('care-assistance.index'))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('care_assistance_applications', 0);
    }

    public function test_application_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.31'])
                ->post(route('care-assistance.store'), [])->assertStatus(302);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.31'])
            ->post(route('care-assistance.store'), [])->assertStatus(429);
    }

    public function test_program_is_discoverable_from_homepage_and_public_indexes(): void
    {
        $url = route('care-assistance.index');
        $this->get('/')->assertOk()->assertSee('href="'.$url.'"', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.$url.'</loc>', false);
        $this->get('/llms.txt')->assertOk()->assertSee($url, false);
        $robots = $this->get('/robots.txt')->assertOk()->assertSee('Allow: /')->assertSee('Disallow: /admin/');
        $robots->assertDontSee('Disallow: /care-assistance')->assertDontSee('Disallow: /images/');
    }

    private function payload(): array
    {
        return [
            'name' => 'Avery Example', 'email' => 'avery@example.test', 'phone' => '(919) 555-0123',
            'relationship' => 'parent', 'city' => 'Raleigh', 'zip' => '27601',
            'care_needs' => ['companionship', 'meal_preparation'], 'weekly_hours' => '5_10',
            'start_timeframe' => 'within_month', 'hardship_details' => 'Our household income has fallen and we need help with everyday care.',
            'consent' => '1', 'website' => '',
        ];
    }
}
