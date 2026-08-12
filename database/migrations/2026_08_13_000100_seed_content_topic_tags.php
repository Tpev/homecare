<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string,string> */
    private const TAGS = [
        'aging-in-place' => 'Practical planning that helps older adults remain connected to familiar homes and routines.',
        'companion-care' => 'Non-medical companionship, errands, meals, routines, and everyday support for older adults.',
        'caregiver-hiring' => 'Guidance for finding, comparing, interviewing, and choosing caregivers and care providers.',
        'safety-and-screening' => 'Background checks, identity, household safeguards, scope boundaries, and safer hiring decisions.',
        'costs-and-payment' => 'Care rates, total-cost comparisons, public programs, insurance, and private-pay planning.',
        'transportation' => 'Local transit programs, appointment rides, errands, accompaniment, and transportation planning.',
        'family-caregiving' => 'Practical support for relatives coordinating care, sharing responsibilities, and preventing burnout.',
        'respite-care' => 'Short-term relief, backup planning, and flexible support for unpaid family caregivers.',
        'care-coordination' => 'Schedules, family updates, visit notes, technology, communication, and shared care plans.',
        'non-medical-care-boundaries' => 'Clear distinctions among companion care, personal care, home health, and clinical services.',
    ];

    public function up(): void
    {
        $now = now();
        $rows = collect(self::TAGS)->map(fn (string $description, string $slug): array => [
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        DB::table('content_tags')->upsert($rows, ['slug'], ['name', 'description', 'updated_at']);
    }

    public function down(): void
    {
        DB::table('content_tags')
            ->whereIn('slug', array_keys(self::TAGS))
            ->whereNotExists(fn (Builder $query): Builder => $query
                ->selectRaw('1')
                ->from('blog_post_tag')
                ->whereColumn('blog_post_tag.content_tag_id', 'content_tags.id'))
            ->delete();
    }
};
