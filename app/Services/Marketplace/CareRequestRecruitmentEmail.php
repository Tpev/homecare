<?php

namespace App\Services\Marketplace;

use App\Models\CareRequest;
use App\Models\User;
use App\Support\MarketplacePricing;
use Illuminate\Support\Str;

class CareRequestRecruitmentEmail
{
    public function __construct(private readonly MarketplacePricing $pricing) {}

    /** @return array{title:string,body:string,payload:array<string,mixed>} */
    public function content(User $caregiver, CareRequest $request): array
    {
        $request->loadMissing(['tasks:id,name', 'carePlan:id,timezone']);
        $timezone = $request->carePlan?->timezone ?: (string) config('app.timezone', 'America/New_York');
        $location = collect([$request->city, $request->state])
            ->map(fn ($part) => trim((string) $part))->filter()->implode(', ');
        $details = [
            ['label' => 'Job', 'value' => $request->title],
            ['label' => 'Care type', 'value' => $request->isRecurring() ? 'Recurring care' : 'One-time care'],
        ];

        if ($location !== '') {
            $details[] = ['label' => 'Area', 'value' => $location];
        }

        if ($request->isRecurring()) {
            $schedule = $request->recurringScheduleLabel();
            if ($schedule !== '') {
                $details[] = ['label' => 'Schedule', 'value' => $schedule.' · '.$timezone];
            }
            if ($request->recurring_starts_on) {
                $dates = 'From '.$request->recurring_starts_on->format('F j, Y');
                if ($request->recurring_ends_on) {
                    $dates .= ' through '.$request->recurring_ends_on->format('F j, Y');
                }
                $details[] = ['label' => 'Dates', 'value' => $dates];
            }
        } elseif ($request->requested_start_at) {
            $start = $request->requested_start_at->copy()->setTimezone($timezone);
            $when = $start->format('l, F j, Y · g:i A T');
            if ($request->requested_end_at) {
                $end = $request->requested_end_at->copy()->setTimezone($timezone);
                $when .= ' – '.$end->format($start->isSameDay($end) ? 'g:i A T' : 'l, F j, Y · g:i A T');

                if ($end->gt($start)) {
                    $minutes = (int) $start->diffInMinutes($end);
                    $hours = intdiv($minutes, 60);
                    $remainder = $minutes % 60;
                    $details[] = ['label' => 'Expected duration', 'value' => collect([
                        $hours > 0 ? $hours.' '.Str::plural('hour', $hours) : null,
                        $remainder > 0 ? $remainder.' '.Str::plural('minute', $remainder) : null,
                    ])->filter()->implode(' ')];
                }
            }
            $details[] = ['label' => 'When', 'value' => $when];
        }

        $tasks = $request->tasks->pluck('name')->filter()->take(5)->implode(', ');
        if ($tasks !== '') {
            $details[] = ['label' => 'Help needed', 'value' => $tasks];
        }

        // Match the gross caregiver rate shown on the application page, never the family budget.
        $details[] = [
            'label' => 'Gross caregiver rate',
            'value' => '$'.number_format($this->pricing->caregiverGrossHourlyCents() / 100, 2).'/hour before Stripe processing fees',
        ];

        return [
            'title' => 'A care opportunity is waiting for the right person',
            'body' => 'This care request is still looking for the right person, and the LoLo team would love for you to take a look.',
            'payload' => [
                'care_request_id' => $request->id,
                'first_name' => (string) Str::of(trim($caregiver->name))->before(' '),
                'preheader' => 'Take a look at '.$request->title.($location !== '' ? ' in '.$location : '').' and apply if it feels like a good fit.',
                'email_details' => $details,
                'opportunity_message' => $request->isRecurring()
                    ? 'This family is looking for someone they can build a regular care relationship with. It could be a chance to get to know a family and make a difference over time.'
                    : 'Sometimes, a few hours of care can be the start of a lasting connection. A smaller job can be a chance to meet a new family, build trust, and open the door to future opportunities.',
                'email_next_steps' => [
                    'If this feels like a good fit, view the job and apply. The family will review your application.',
                ],
            ],
        ];
    }
}
