<?php

namespace Tests\Feature\Marketing;

use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
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

        $response->assertSee(route('landing.get-care'), false);
        $response->assertSee(route('register'), false);
        $response->assertSee(route('caregiver.register'), false);
        $response->assertSee(route('caregivers.search'), false);
        $response->assertSeeText('Caregiver? Join LoLo');
        $response->assertSee('href="'.route('register').'">Find care', false);
        $response->assertDontSee('href="#booking">Find care', false);
        $response->assertSee('What kind of help do you need?');
        $response->assertSee('name="care_schedule"', false);
        $response->assertSee('$30/hr');
        $response->assertSee('https://www.youtube-nocookie.com/embed/_nve3ZnFsGM?rel=0', false);
        $response->assertSee('See how LoLo works for families.');
        $response->assertSee(asset('images/marketing/lolo-hero.jpg'), false);
        $response->assertSee(asset('images/marketing/lolo/lolo-wordmark-evergreen.svg'), false);
    }

    public function test_landing_showcases_real_eligible_caregiver_data_only(): void
    {
        $caregiver = User::factory()->create([
            'name' => 'Jordan Actual Caregiver',
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => 'jordan-actual-caregiver',
            'status' => 'active',
            'bio' => 'Calm companionship, meal preparation, and reliable daily routines.',
            'platform_hourly_rate' => 32,
            'years_experience' => 8,
            'service_area_zip' => '27601',
            'service_radius_miles' => 20,
            'is_accepting_new_clients' => true,
            'average_rating' => 4.9,
            'reviews_count' => 12,
            'identity_verified_at' => now(),
            'background_check_verified_at' => now(),
            'top_caregiver' => true,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
        ]);
        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);
        $profile->skills()->attach($skill);
        $profile->languages()->attach($language);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $incomplete = User::factory()->create([
            'name' => 'Hidden Incomplete Caregiver',
            'role' => 'caregiver',
        ]);
        CaregiverProfile::query()->create([
            'user_id' => $incomplete->id,
            'slug' => 'hidden-incomplete-caregiver',
            'status' => 'active',
            'bio' => 'This incomplete profile must not leak onto the homepage.',
            'platform_hourly_rate' => 30,
            'years_experience' => 3,
            'is_accepting_new_clients' => true,
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSeeText('Jordan Actual Caregiver')
            ->assertSeeText('8 years of experience')
            ->assertSeeText('Companionship')
            ->assertSeeText('Raleigh, NC')
            ->assertSeeText('$32/hr')
            ->assertSee(route('caregivers.show', ['slug' => $profile->slug]), false)
            ->assertDontSeeText('Hidden Incomplete Caregiver')
            ->assertDontSeeText('Maria S.')
            ->assertDontSeeText('Angela R.')
            ->assertDontSeeText('Denise T.');
    }

    public function test_landing_does_not_expose_caregivers_during_prelaunch(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => true]);
        $caregiver = User::factory()->create(['name' => 'Prelaunch Private Caregiver', 'role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => 'prelaunch-private-caregiver',
            'status' => 'active',
            'bio' => 'Complete but hidden while prelaunch is enabled.',
            'platform_hourly_rate' => 30,
            'years_experience' => 4,
            'service_area_zip' => '27601',
            'service_radius_miles' => 20,
            'is_accepting_new_clients' => true,
        ]);
        $skill = Skill::query()->create(['name' => 'Prelaunch companionship']);
        $language = Language::query()->create(['name' => 'Prelaunch English']);
        $profile->skills()->attach($skill);
        $profile->languages()->attach($language);
        $profile->availabilities()->create([
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertDontSeeText('Prelaunch Private Caregiver')
            ->assertSeeText('Caregiver profiles are being updated.');
    }

    public function test_caregiver_landing_uses_lolo_positioning(): void
    {
        $response = $this->get(route('landing.caregiver'));

        $response->assertOk()
            ->assertSee('Caregiving work that fits your', false)
            ->assertSee('Create your caregiver profile')
            ->assertSee('Starting family rate')
            ->assertSee(route('caregiver.register'), false)
            ->assertDontSee('HomeCare')
            ->assertDontSee('AI-guided')
            ->assertDontSee('Text LoLo');
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
