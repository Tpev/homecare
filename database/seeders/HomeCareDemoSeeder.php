<?php

namespace Database\Seeders;

use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestMessage;
use App\Models\CareTask;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HomeCareDemoSeeder extends Seeder
{
    public function run(): void
    {
        $family = User::query()->updateOrCreate(
            ['email' => 'family.demo@homecare.test'],
            [
                'name' => 'Emma Johnson',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'family',
                'phone' => '+1 919 555 0110',
                'city' => 'Raleigh',
                'state' => 'NC',
            ]
        );

        $caregiver = User::query()->updateOrCreate(
            ['email' => 'caregiver.demo@homecare.test'],
            [
                'name' => 'Michael Rivera',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'caregiver',
                'phone' => '+1 919 555 0155',
                'city' => 'Raleigh',
                'state' => 'NC',
                'date_of_birth' => '1992-06-15',
                'onboarding_completed_at' => now(),
            ]
        );

        $profile = CaregiverProfile::query()->updateOrCreate(
            ['user_id' => $caregiver->id],
            [
                'slug' => Str::slug($caregiver->name.'-'.$caregiver->id),
                'bio' => 'Non-medical caregiver with experience in companionship, meal prep, mobility support, and daily routines.',
                'hourly_rate' => 28.00,
                'years_experience' => 6,
                'service_area_zip' => '27601',
                'service_radius_miles' => 15,
                'is_accepting_new_clients' => true,
                'status' => 'active',
                'review_submitted_at' => now()->subDays(5),
                'reviewed_at' => now()->subDays(4),
                'rejection_reason' => null,
                'average_rating' => 4.80,
                'reviews_count' => 12,
            ]
        );

        $skillNames = [
            'Companionship',
            'Meal preparation',
            'Medication reminders',
            'Transportation',
        ];
        $skillIds = collect($skillNames)->map(function (string $name) {
            return Skill::query()->firstOrCreate(['name' => $name])->id;
        })->all();
        $profile->skills()->sync($skillIds);

        $languageNames = ['English', 'Spanish'];
        $languageIds = collect($languageNames)->map(function (string $name) {
            return Language::query()->firstOrCreate(['name' => $name])->id;
        })->all();
        $profile->languages()->sync($languageIds);

        $profile->availabilities()->delete();
        $profile->availabilities()->createMany([
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '13:00'],
            ['day_of_week' => 3, 'start_time' => '10:00', 'end_time' => '16:00'],
            ['day_of_week' => 5, 'start_time' => '08:00', 'end_time' => '12:00'],
        ]);

        $start = Carbon::now()->addDays(2)->setTime(9, 0);
        $end = Carbon::now()->addDays(2)->setTime(13, 0);

        $request = CareRequest::query()->updateOrCreate(
            [
                'family_user_id' => $family->id,
                'title' => 'Weekday morning companionship and meal prep',
            ],
            [
                'additional_info' => 'Looking for calm, patient support. Recipient likes short walks and simple card games.',
                'status' => CareRequest::STATUS_OPEN,
                'budget_min' => 24.00,
                'budget_max' => 32.00,
                'requested_start_at' => $start,
                'requested_end_at' => $end,
                'address_line1' => '123 Oak Ridge Ave',
                'address_line2' => 'Apt 2B',
                'city' => 'Raleigh',
                'state' => 'NC',
                'zip' => '27601',
            ]
        );

        $request->recipient()->updateOrCreate(
            ['care_request_id' => $request->id],
            [
                'full_name' => 'Margaret Johnson',
                'date_of_birth' => '1948-04-17',
                'gender' => 'female',
                'mobility_level' => 'standby_support',
                'relationship_to_family' => 'Mother',
                'care_notes' => 'Needs medication reminders and standby support when walking to kitchen.',
            ]
        );

        $request->thirdPartyContact()->updateOrCreate(
            ['care_request_id' => $request->id],
            [
                'full_name' => 'Daniel Johnson',
                'relationship_to_recipient' => 'Son',
                'phone' => '+1 919 555 0142',
                'email' => 'daniel.johnson@example.com',
            ]
        );

        $taskIds = collect([
            'Companionship' => 'Morning routine, conversation, light activities.',
            'Meal preparation' => 'Prepare breakfast and lunch.',
            'Medication reminders' => 'Reminder only, no clinical tasks.',
        ])->mapWithKeys(function (string $note, string $taskName) {
            $task = CareTask::query()->firstOrCreate(['name' => $taskName]);

            return [$task->id => ['task_note' => $note]];
        })->all();
        $request->tasks()->sync($taskIds);

        $application = CareRequestApplication::query()->updateOrCreate(
            [
                'care_request_id' => $request->id,
                'caregiver_user_id' => $caregiver->id,
            ],
            [
                'status' => CareRequestApplication::STATUS_SHORTLISTED,
                'proposed_rate' => 29.00,
                'cover_note' => 'I have 6 years of non-medical home care experience and can cover this schedule consistently.',
            ]
        );

        $conversation = CareRequestConversation::query()->updateOrCreate(
            [
                'care_request_id' => $request->id,
                'caregiver_user_id' => $caregiver->id,
            ],
            [
                'family_user_id' => $family->id,
                'care_request_application_id' => $application->id,
                'started_by_user_id' => $family->id,
                'family_last_read_at' => now()->subMinutes(2),
                'caregiver_last_read_at' => now()->subMinutes(2),
                'last_message_at' => now()->subMinute(),
                'last_message_sender_id' => $caregiver->id,
            ]
        );

        CareRequestMessage::query()->updateOrCreate(
            [
                'care_request_conversation_id' => $conversation->id,
                'sender_user_id' => $family->id,
                'body' => 'Hi Michael, thanks for applying. Are you available every Monday and Wednesday morning?',
            ],
            ['created_at' => now()->subMinutes(4), 'updated_at' => now()->subMinutes(4)]
        );

        CareRequestMessage::query()->updateOrCreate(
            [
                'care_request_conversation_id' => $conversation->id,
                'sender_user_id' => $caregiver->id,
                'body' => 'Yes, I am available both mornings and can start this week.',
            ],
            ['created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()]
        );
    }
}
