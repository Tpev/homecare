<?php

namespace Database\Seeders;

use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        foreach ([
            'English', 'Spanish', 'French', 'Chinese (Mandarin)', 'Vietnamese',
            'Arabic', 'Tagalog', 'Korean', 'Russian', 'Portuguese', 'Hindi',
        ] as $languageName) {
            Language::query()->firstOrCreate(['name' => $languageName]);
        }

        foreach ([
            'Companionship',
            'Meal preparation',
            'Light housekeeping',
            'Transportation',
            'Medication reminders',
            'Errands',
            'Daily living assistance',
        ] as $skillName) {
            Skill::query()->firstOrCreate(['name' => $skillName]);
        }

        foreach ([
            'Companionship',
            'Meal preparation',
            'Light housekeeping',
            'Transportation',
            'Medication reminders',
            'Errands',
            'Daily living assistance',
        ] as $taskName) {
            CareTask::query()->firstOrCreate(['name' => $taskName]);
        }

        $this->call(HomeCareDemoSeeder::class);
        $this->call(FamilyAcquisitionDemoSeeder::class);
    }
}
