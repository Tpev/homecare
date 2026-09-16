<?php

namespace Database\Seeders;

use App\Models\CareTask;
use Illuminate\Database\Seeder;

class CareTaskSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Companionship',
            'Meal preparation',
            'Light housekeeping',
            'Transportation',
            'Medication reminders',
            'Errands',
            'Daily living assistance',
        ] as $name) {
            CareTask::query()->firstOrCreate(['name' => $name]);
        }
    }
}
