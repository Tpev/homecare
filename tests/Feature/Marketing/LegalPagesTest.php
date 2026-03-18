<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_family_and_agency_routes_redirect_to_caregiver_landing(): void
    {
        $this->get('/')->assertRedirect(route('landing.caregiver'));
        $this->get('/families')->assertRedirect(route('landing.caregiver'));
        $this->get('/agencies')->assertRedirect(route('landing.caregiver'));
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

