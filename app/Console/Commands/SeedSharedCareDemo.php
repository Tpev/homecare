<?php

namespace App\Console\Commands;

use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use App\Services\RegularCare\CarePlanService;
use App\Services\RegularCare\SharedCareService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedSharedCareDemo extends Command
{
    protected $signature = 'homecare:shared-care-demo';

    protected $description = 'Create a local-only shared-care demo in the isolated SQLite preview database.';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing') || config('database.default') !== 'sqlite'
            || basename((string) config('database.connections.sqlite.database')) !== 'shared-care-local.sqlite'
            || ! config('services.stripe.bypass')) {
            $this->error('Use tools/start-shared-care-local.ps1. This demo requires its isolated SQLite database and simulated payments.');

            return self::FAILURE;
        }
        $family = User::firstOrCreate(['email' => 'john.shared@example.test'], ['name' => 'John Shared Care', 'role' => 'family', 'email_verified_at' => now(),
            'password' => Hash::make('LocalCare2026!'), 'city' => 'Raleigh', 'state' => 'NC']);
        $account = app(FamilyAccountProvisioner::class)->provisionOwner($family, 'shared_care_demo');
        $caregivers = [];
        $skill = Skill::firstOrCreate(['name' => 'Companionship']);
        $language = Language::firstOrCreate(['name' => 'English']);
        foreach (['Alice', 'Ben', 'Carla'] as $name) {
            $user = User::firstOrCreate(['email' => strtolower($name).'.shared@example.test'], ['name' => $name.' Demo', 'role' => 'caregiver', 'email_verified_at' => now(),
                'onboarding_completed_at' => now(), 'date_of_birth' => '1990-01-01', 'password' => Hash::make('LocalCare2026!'), 'phone' => '+19195550100', 'city' => 'Raleigh', 'state' => 'NC']);
            $profile = CaregiverProfile::firstOrCreate(['user_id' => $user->id], ['slug' => strtolower($name).'-shared-demo', 'status' => 'active',
                'bio' => str_repeat('Experienced caregiver providing reliable companionship and everyday support. ', 3),
                'platform_hourly_rate' => 28, 'hourly_rate' => 28, 'years_experience' => 6, 'service_area_zip' => '27601', 'service_radius_miles' => 12,
                'is_accepting_new_clients' => true, 'insurance_status' => CaregiverProfile::INSURANCE_NO, 'identity_verified_at' => now(),
                'identity_verification_status' => 'approved', 'background_check_verified_at' => now()]);
            $profile->skills()->syncWithoutDetaching([$skill->id]);
            $profile->languages()->syncWithoutDetaching([$language->id]);
            foreach (range(0, 6) as $day) {
                $profile->availabilities()->firstOrCreate(['day_of_week' => $day], ['start_time' => '08:00', 'end_time' => '18:00']);
            }
            $caregivers[$name] = $user;
        }
        $request = CareRequest::firstOrCreate(['family_account_id' => $account->id, 'title' => 'John’s Monday–Saturday care · local demo'], [
            'family_user_id' => $family->id, 'request_type' => 'recurring', 'status' => 'open', 'recurring_starts_on' => now()->next('Monday')->toDateString(),
            'recurring_days' => [1, 2, 3, 4, 5, 6], 'recurring_start_time' => '08:00', 'recurring_end_time' => '14:00',
            'recurring_schedule' => array_map(fn ($day) => ['day' => $day, 'start_time' => '08:00', 'end_time' => '14:00'], [1, 2, 3, 4, 5, 6]),
            'address_line1' => '123 Demo Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'scope_of_work' => 'Companionship, meals and everyday support. This is fictional local test data.',
        ]);
        $request->recipient()->firstOrCreate([], ['full_name' => 'John', 'relationship_to_family' => 'Self']);
        $task = CareTask::firstOrCreate(['name' => 'Companionship']);
        $request->tasks()->syncWithoutDetaching([$task->id]);
        $service = app(SharedCareService::class);
        if (! $request->care_arrangement_id) {
            $application = CareRequestApplication::updateOrCreate(['care_request_id' => $request->id, 'caregiver_user_id' => $caregivers['Alice']->id],
                ['status' => 'applied', 'proposed_rate' => 28, 'selected_days' => [1, 3, 5], 'offered_schedule' => $service->offer($request, [1, 3, 5]), 'schedule_fingerprint' => $service->fingerprint($request)]);
            app(CarePlanService::class)->activateFromRecurringRequest($request, $application, $family);
        }
        $request->refresh();
        if (! $request->applications()->where('caregiver_user_id', $caregivers['Ben']->id)->exists() && $request->status === 'open') {
            CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregivers['Ben']->id, 'status' => 'applied', 'proposed_rate' => 28,
                'selected_days' => [2, 4, 6], 'offered_schedule' => $service->offer($request, [2, 4, 6]), 'schedule_fingerprint' => $service->fingerprint($request), 'cover_note' => 'I can cover Tuesday, Thursday and Saturday every week.']);
        }
        $this->info('Shared care: '.route('family.shared-care.show', $request->care_arrangement_id));
        $this->line('Applicants: '.route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'applicants']));
        $this->line('Caregiver application: '.route('care-requests.apply', $request->id));
        $this->line('Family: john.shared@example.test | Caregivers: alice.shared@example.test, ben.shared@example.test, carla.shared@example.test');
        $this->line('Password for these local demo accounts: LocalCare2026!');

        return self::SUCCESS;
    }
}
