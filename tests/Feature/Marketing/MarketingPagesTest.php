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
}
