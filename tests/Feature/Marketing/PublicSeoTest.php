<?php

namespace Tests\Feature\Marketing;

use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.caregiver_prelaunch_mode' => false]);
    }

    public function test_public_schemas_have_real_contexts_and_safe_serialization(): void
    {
        $answer = 'Help with meals & companionship, including a literal </script><script>alert("test")</script> example.';
        config(['marketing.faq_categories' => [['slug' => 'care', 'title' => 'Care', 'description' => 'Support at home.', 'faqs' => [['question' => 'What is included?', 'answer' => $answer]]]]]);

        foreach (['/', '/faq', '/caregivers', '/about'] as $path) {
            $response = $this->get($path)->assertOk();
            $dom = $this->dom($response);
            $scripts = $dom->query('//script[@type="application/ld+json"]');
            $this->assertGreaterThan(0, $scripts->length, $path);
            foreach ($scripts as $script) {
                $schema = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('https://schema.org', $schema['@context'] ?? null, $path);
                $this->assertStringNotContainsString('<?php', $script->textContent);
                if ($path === '/faq') {
                    $this->assertSame($answer, $schema['mainEntity'][0]['acceptedAnswer']['text']);
                }
            }
        }
    }

    public function test_every_service_page_has_current_branding_unique_metadata_and_valid_schema(): void
    {
        $titles = [];
        foreach (array_keys(config('seo_pages.pages')) as $slug) {
            $url = route('seo.page', ['seoSlug' => $slug]);
            $response = $this->get($url)->assertOk();
            $dom = $this->dom($response);
            $title = $dom->evaluate('string(//title)');
            $this->assertStringContainsString('LoLo Care', $title);
            $this->assertNotContains($title, $titles);
            $titles[] = $title;
            $this->assertSame(1, $dom->query('//title')->length);
            $this->assertSame($url, $dom->evaluate('string(//link[@rel="canonical"]/@href)'));
            $this->assertSame($url, $dom->evaluate('string(//meta[@property="og:url"]/@content)'));
            $this->assertStringNotContainsString('HomeCare', $response->getContent());
            $this->assertStringNotContainsString('All Raleigh SEO pages', $response->getContent());
            $this->assertSame(0, $dom->query('//a[contains(@href,"/families")]')->length);
            foreach ($dom->query('//script[@type="application/ld+json"]') as $script) {
                $schema = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('https://schema.org', $schema['@context'] ?? null);
            }
        }
    }

    public function test_complete_profiles_have_unique_escaped_metadata_and_clean_canonicals(): void
    {
        foreach (['Avery "AJ" & Lane', 'Jamie Rivers'] as $name) {
            $profile = $this->profile($name);
            $url = route('caregivers.show', ['slug' => $profile->slug]);
            $dom = $this->dom($this->get($url.'?utm_source=profile-share')->assertOk());
            $title = $name.' — Raleigh, NC caregiver | LoLo Care';
            $this->assertSame($title, $dom->evaluate('string(//title)'));
            $this->assertSame(1, $dom->query('//title')->length);
            $this->assertSame($title, $dom->evaluate('string(//meta[@property="og:title"]/@content)'));
            $this->assertSame('index,follow', $dom->evaluate('string(//meta[@name="robots"]/@content)'));
            $this->assertSame($url, $dom->evaluate('string(//link[@rel="canonical"]/@href)'));
            $this->assertSame($url, $dom->evaluate('string(//meta[@property="og:url"]/@content)'));
            $this->assertStringContainsString('companionship', $dom->evaluate('string(//meta[@name="description"]/@content)'));
        }
    }

    public function test_thin_incomplete_and_discovery_excluded_profiles_remain_accessible_but_noindex(): void
    {
        $profile = $this->profile('Jordan Lane');
        $url = route('caregivers.show', ['slug' => $profile->slug]);
        config(['marketplace.caregiver_discovery_excluded_slugs' => [$profile->slug]]);
        $this->assertNoindex($this->get($url)->assertOk());
        config(['marketplace.caregiver_discovery_excluded_slugs' => []]);

        $originalBio = $profile->bio;
        $profile->update(['bio' => 'Happy to help.']);
        $this->assertNoindex($this->get($url)->assertOk());
        $profile->update(['bio' => $originalBio]);
        $profile->languages()->detach();
        $this->assertNoindex($this->get($url)->assertOk());

        $profile->user->update(['city' => null, 'state' => null]);
        $dom = $this->dom($this->get($url)->assertOk());
        $this->assertSame('Jordan Lane — Caregiver profile | LoLo Care', $dom->evaluate('string(//title)'));
        $this->assertSame('noindex,follow', $dom->evaluate('string(//meta[@name="robots"]/@content)'));
    }

    public function test_inactive_and_prelaunch_profiles_are_not_exposed(): void
    {
        $profile = $this->profile('Jordan Lane');
        $url = route('caregivers.show', ['slug' => $profile->slug]);
        $profile->update(['status' => 'draft']);
        $this->get($url)->assertNotFound();
        $profile->update(['status' => 'active']);
        config(['marketplace.caregiver_prelaunch_mode' => true]);
        $this->get($url)->assertNotFound();
    }

    public function test_directory_filters_are_noindex_but_campaign_tags_are_not(): void
    {
        $url = route('caregivers.search');
        foreach (['', '?utm_source=mail&gclid=example'] as $query) {
            $dom = $this->dom($this->get($url.$query)->assertOk());
            $this->assertSame('Find Caregivers in Raleigh & the Triangle | LoLo Care', $dom->evaluate('string(//title)'));
            $this->assertSame('index,follow', $dom->evaluate('string(//meta[@name="robots"]/@content)'));
            $this->assertSame($url, $dom->evaluate('string(//link[@rel="canonical"]/@href)'));
        }
        foreach (['?page=2', '?certifications[]=cna', '?search=Jordan', '?sort=price_low'] as $query) {
            $this->assertNoindex($this->get($url.$query)->assertOk());
        }
        config(['marketplace.caregiver_prelaunch_mode' => true]);
        $this->assertNoindex($this->get($url)->assertOk());
    }

    public function test_discovery_files_do_not_promote_redirects_or_noindex_pages(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk()
            ->assertDontSee('/families')
            ->assertSee(route('caregivers.search'), false);
        foreach (array_keys(config('legal_pages.pages')) as $slug) {
            $response->assertDontSee(route('legal.show', ['slug' => $slug]), false);
        }
        $this->get('/llms.txt')->assertOk()->assertDontSee('/families');
        config(['marketplace.caregiver_prelaunch_mode' => true]);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(route('caregivers.search'), false);
    }

    private function assertNoindex(TestResponse $response): void
    {
        $this->assertSame('noindex,follow', $this->dom($response)->evaluate('string(//meta[@name="robots"]/@content)'));
    }

    private function dom(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function profile(string $name): CaregiverProfile
    {
        $user = User::factory()->create(['name' => $name, 'role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $user->id,
            'slug' => 'caregiver-'.$user->id,
            'status' => 'active',
            'bio' => 'I provide thoughtful companionship, meal preparation, errands and support with everyday routines. I enjoy helping older adults feel comfortable and connected at home.',
            'platform_hourly_rate' => 30,
            'years_experience' => 4,
            'service_area_zip' => '27601',
            'service_radius_miles' => 20,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
        ]);
        $profile->skills()->attach(Skill::query()->firstOrCreate(['name' => 'Companionship']));
        $profile->languages()->attach(Language::query()->firstOrCreate(['name' => 'English']));
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00']);

        return $profile;
    }
}
