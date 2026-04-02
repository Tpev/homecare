<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_pages_render_successfully(): void
    {
        $this->get(route('landing'))->assertOk();
        $this->get(route('landing.family'))->assertOk();
        $this->get(route('landing.family.variant', ['variant' => 'a']))->assertOk();
        $this->get(route('landing.family.variant', ['variant' => 'b']))->assertOk();
        $this->get(route('landing.family.variant', ['variant' => 'c']))->assertOk();
        $this->get(route('landing.family.variant', ['variant' => 'd']))->assertOk();
        $this->get(route('landing.family.variant', ['variant' => 'e']))->assertOk();
        $this->get(route('landing.caregiver'))->assertOk();
        $this->get(route('landing.agency'))->assertOk();
    }

    public function test_landing_has_registration_ctas(): void
    {
        $response = $this->get(route('landing'));

        $response->assertSee(route('register'), false);
        $response->assertSee(route('landing.caregiver'), false);
        $response->assertSee(route('login'), false);
        $response->assertDontSee(route('caregivers.search'), false);
    }

    public function test_family_variant_pages_have_clear_primary_ctas(): void
    {
        foreach (['a', 'b', 'c', 'd', 'e'] as $variant) {
            $this->get(route('landing.family.variant', ['variant' => $variant]))
                ->assertSee(route('register'), false)
                ->assertSee(route('login'), false);
        }
    }
}
