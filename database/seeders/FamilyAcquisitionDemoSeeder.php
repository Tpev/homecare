<?php

namespace Database\Seeders;

use App\Models\FamilyAcquisitionSetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MarketingSpendDaily;
use App\Models\User;
use App\Support\FamilyLeadOutreach;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FamilyAcquisitionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin.acquisition@lolo.test'],
            [
                'name' => 'Maya Bennett',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'admin',
            ]
        );

        $sdr = User::query()->updateOrCreate(
            ['email' => 'sdr.family@lolo.test'],
            [
                'name' => 'Jordan Lee',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'sdr',
            ]
        );

        FamilyAcquisitionSetting::query()->firstOrCreate(['id' => 1], [
            'alerts_enabled' => true,
            'new_lead_alert_emails' => 'sdr.family@lolo.test',
            'escalation_alert_emails' => 'admin.acquisition@lolo.test',
            'first_call_sla_minutes' => 15,
            'updated_by_user_id' => $admin->id,
        ]);

        $campaigns = [
            'cmp_respite_01' => [
                'name' => 'Respite support • Raleigh',
                'ad_set' => 'Adult children 45–64 • 25 mi',
                'ad' => 'A few hours to breathe',
                'form' => 'Find support for a parent',
                'daily_spend' => 2800,
            ],
            'cmp_discharge_02' => [
                'name' => 'After-hospital help • Triangle',
                'ad_set' => 'Care transition families',
                'ad' => 'Coming home should feel supported',
                'form' => 'Help after discharge',
                'daily_spend' => 2200,
            ],
            'cmp_companion_03' => [
                'name' => 'Companionship • Wake County',
                'ad_set' => 'Local family caregivers',
                'ad' => 'Someone showing up',
                'form' => 'Talk with a care guide',
                'daily_spend' => 1800,
            ],
        ];

        foreach (range(0, 29) as $daysAgo) {
            foreach ($campaigns as $campaignId => $campaign) {
                MarketingSpendDaily::query()->updateOrCreate(
                    [
                        'spend_date' => now()->subDays($daysAgo)->toDateString(),
                        'channel' => 'meta',
                        'campaign_id' => $campaignId,
                    ],
                    [
                        'campaign_name' => $campaign['name'],
                        'ad_set_name' => $campaign['ad_set'],
                        'ad_name' => $campaign['ad'],
                        'spend_cents' => $campaign['daily_spend'] + (($daysAgo % 4) * 125),
                        'impressions' => 2800 + (($daysAgo % 7) * 175),
                        'clicks' => 38 + ($daysAgo % 9),
                        'currency' => 'USD',
                    ]
                );
            }
        }

        $leads = [
            ['FL-1001', 'Claire Thompson', '919-555-0101', 0, null, 'new', 0, null, 'cmp_respite_01', 'instagram', 'Mother', 'Evelyn, 78', ['Companionship', 'Meal preparation'], 'This week', 'Weekday afternoons', 'Private pay', 'Mom has become isolated since she stopped driving.', 'urgent', false],
            ['FL-1002', 'Marcus Green', '919-555-0102', 0, null, 'new', 0, null, 'cmp_discharge_02', 'facebook', 'Father', 'Robert, 81', ['Mobility support', 'Meal preparation', 'Check-ins'], 'Within 48 hours', 'Daily mornings', 'Long-term care insurance', 'Dad is being discharged on Thursday.', 'urgent', false],
            ['FL-1003', 'Alicia Ramirez', '919-555-0103', 1, null, 'new', 0, null, 'cmp_companion_03', 'instagram', 'Aunt', 'Rosa, 74', ['Companionship', 'Transportation'], 'Exploring options', 'Two afternoons weekly', 'Not sure yet', 'Looking for someone bilingual if possible.', 'normal', false],
            ['FL-1004', 'Daniel Foster', '919-555-0104', 3, 6, 'attempting_contact', 1, 'voicemail_left', 'cmp_respite_01', 'facebook', 'Spouse', 'Anne, 71', ['Respite', 'Personal routine support'], 'This week', 'Saturday mornings', 'Private pay', 'I need predictable respite to keep working.', 'high', true],
            ['FL-1005', 'Priya Shah', '919-555-0105', 5, 12, 'attempting_contact', 2, 'no_answer', 'cmp_discharge_02', 'instagram', 'Mother', 'Meena, 82', ['Meal preparation', 'Medication reminders'], 'Within 2 weeks', 'Mon/Wed/Fri mornings', 'Private pay', 'My mother lives alone and needs a steady routine.', 'high', false],
            ['FL-1006', 'Owen Brooks', '919-555-0106', 7, 18, 'attempting_contact', 4, 'voicemail_left', 'cmp_companion_03', 'facebook', 'Father', 'Harold, 86', ['Companionship', 'Light housekeeping'], 'Within a month', 'Flexible afternoons', 'Veteran benefits', 'Dad would benefit from conversation and light help.', 'normal', false],
            ['FL-1007', 'Natalie Kim', '919-555-0107', 9, 32, 'attempting_contact', 6, 'no_answer', 'cmp_respite_01', 'instagram', 'Grandmother', 'Sun-ja, 88', ['Companionship', 'Check-ins'], 'This week', 'Early evenings', 'Family funded', 'Please call after 5 if possible.', 'high', true],
            ['FL-1008', 'Henry Collins', '919-555-0108', 4, 9, 'callback_scheduled', 1, 'callback_requested', 'cmp_discharge_02', 'facebook', 'Mother', 'Joyce, 79', ['Transportation', 'Meal preparation'], 'Within 2 weeks', 'Tuesday/Thursday', 'Private pay', 'Asked us to call when his sister can join.', 'normal', true],
            ['FL-1009', 'Sophie Nguyen', '919-555-0109', 6, 15, 'callback_scheduled', 2, 'callback_requested', 'cmp_respite_01', 'instagram', 'Father', 'Minh, 83', ['Respite', 'Companionship'], 'This week', 'Weekends', 'Private pay', 'Needs to confirm timing with her father.', 'high', false],
            ['FL-1010', 'James Wilson', '919-555-0110', 8, 4, 'qualified', 1, 'connected_qualified', 'cmp_companion_03', 'facebook', 'Mother', 'Patricia, 76', ['Companionship', 'Transportation'], 'Within 2 weeks', 'Three afternoons weekly', 'Private pay', 'Good fit; wants a local caregiver who drives.', 'normal', true],
            ['FL-1011', 'Erin Wallace', '919-555-0111', 11, 22, 'qualified', 2, 'connected_qualified', 'cmp_discharge_02', 'instagram', 'Father', 'Frank, 84', ['Meal preparation', 'Mobility support'], 'Within 48 hours', 'Daily for first two weeks', 'Long-term care insurance', 'Hospital discharge plan is confirmed.', 'urgent', true],
            ['FL-1012', 'Michael Reed', '919-555-0112', 12, 8, 'assessment_scheduled', 2, 'assessment_booked', 'cmp_respite_01', 'facebook', 'Spouse', 'Linda, 69', ['Respite', 'Personal routine support'], 'This week', 'Mon–Fri 1–5pm', 'Private pay', 'Assessment booked with both adult children joining.', 'high', true],
            ['FL-1013', 'Tanya Moore', '919-555-0113', 15, 14, 'assessment_scheduled', 1, 'assessment_booked', 'cmp_companion_03', 'instagram', 'Mother', 'Gloria, 80', ['Companionship', 'Errands'], 'Within a month', 'Twice weekly', 'Private pay', 'Family wants to start with a short weekly schedule.', 'normal', false],
            ['FL-1014', 'Victor Alvarez', '919-555-0114', 18, 5, 'converted', 2, 'assessment_booked', 'cmp_discharge_02', 'facebook', 'Father', 'Luis, 77', ['Mobility support', 'Meal preparation'], 'Within 48 hours', 'Daily mornings', 'Private pay', 'Care started after a successful assessment.', 'urgent', true],
            ['FL-1015', 'Rachel Price', '919-555-0115', 21, 11, 'converted', 1, 'assessment_booked', 'cmp_respite_01', 'instagram', 'Mother', 'Diane, 73', ['Respite', 'Companionship'], 'This week', 'Saturday and Sunday', 'Family funded', 'Weekend care began last week.', 'high', true],
            ['FL-1016', 'George Patel', '919-555-0116', 25, 28, 'converted', 3, 'assessment_booked', 'cmp_companion_03', 'facebook', 'Uncle', 'Ravi, 75', ['Transportation', 'Errands'], 'Within a month', 'Flexible weekdays', 'Private pay', 'First recurring visit completed.', 'normal', false],
            ['FL-1017', 'Bethany Scott', '919-555-0117', 14, 41, 'unreachable', 7, 'no_answer', 'cmp_respite_01', 'instagram', 'Mother', 'Carol, 81', ['Respite', 'Check-ins'], 'This week', 'Unknown', 'Not sure yet', 'Seven attempts completed without contact.', 'normal', false],
            ['FL-1018', 'Noah Edwards', '919-555-0118', 17, 64, 'unreachable', 7, 'voicemail_left', 'cmp_discharge_02', 'facebook', 'Father', 'Samuel, 85', ['Meal preparation', 'Check-ins'], 'Within 2 weeks', 'Unknown', 'Private pay', 'Voicemails left; no response after seven business days.', 'normal', false],
            ['FL-1019', 'Camille Davis', '919-555-0119', 13, 7, 'nurture', 1, 'not_ready', 'cmp_companion_03', 'instagram', 'Mother', 'Janet, 72', ['Companionship'], 'Exploring options', 'Not decided', 'Private pay', 'Family asked us to reconnect after an upcoming move.', 'low', false],
            ['FL-1020', 'Elliot Brown', '919-555-0120', 10, 16, 'not_fit', 1, 'not_eligible', 'cmp_respite_01', 'facebook', 'Father', 'Arthur, 80', ['Skilled nursing'], 'Within 48 hours', 'Overnight', 'Medicare', 'Needs clinical nursing outside LoLo scope.', 'normal', false],
            ['FL-1021', 'Megan Hart', '919-555-0121', 9, 10, 'closed', 1, 'do_not_contact', 'cmp_companion_03', 'instagram', 'Mother', 'Joan, 79', ['Companionship'], 'Exploring options', 'Unknown', 'Not sure yet', 'Asked not to receive additional calls.', 'low', false],
            ['FL-1022', 'Thomas King', '919-555-0122', 2, null, 'new', 0, null, null, 'manual_crm', 'Father', 'William, 87', ['Companionship', 'Transportation'], 'This week', 'Weekday mornings', 'Private pay', 'Entered manually after a community event enquiry.', 'high', false],
        ];

        foreach ($leads as $index => $row) {
            [
                $externalId, $name, $phone, $daysAgo, $responseMinutes, $status, $attempts, $lastOutcome,
                $campaignId, $platform, $relationship, $careFor, $needs, $urgency, $schedule, $funding,
                $details, $priority, $owned,
            ] = $row;

            $submittedAt = now()->subDays($daysAgo)->setTime(9 + ($index % 7), ($index * 7) % 60);
            $firstCallAt = $responseMinutes !== null ? $submittedAt->copy()->addMinutes($responseMinutes) : null;
            $campaign = $campaignId ? $campaigns[$campaignId] : null;
            $source = $campaignId ? 'meta_lead_ads' : 'manual_crm';
            $lastCallAt = $attempts > 0 ? $firstCallAt?->copy()->addDays(max(0, $attempts - 1)) : null;
            $isConnected = $lastOutcome && FamilyLeadOutreach::isConnected($lastOutcome);
            $firstConnectedAt = $isConnected ? $lastCallAt : null;
            $convertedAt = $status === 'converted' ? $lastCallAt?->copy()->addDays(2) : null;
            $nextFollowUpAt = match ($status) {
                'attempting_contact' => $owned ? now()->subMinutes(20 + $index) : now()->subHours(1 + ($index % 3)),
                'callback_scheduled' => now()->addHours(2 + $index),
                'nurture' => now()->addDays(10),
                default => null,
            };

            $meta = $campaign ? [
                'platform' => $platform,
                'lead_id' => 'meta_'.$externalId,
                'campaign_id' => $campaignId,
                'campaign_name' => $campaign['name'],
                'ad_set_name' => $campaign['ad_set'],
                'ad_name' => $campaign['ad'],
                'form_name' => $campaign['form'],
                'submitted_at' => $submittedAt->toISOString(),
            ] : null;

            $formAnswers = [
                'relationship' => $relationship,
                'care_for' => $careFor,
                'care_needs' => $needs,
                'urgency' => $urgency,
                'schedule' => $schedule,
                'funding' => $funding,
                'preferred_call_time' => $index % 3 === 0 ? 'After 4pm' : 'Any time today',
                'additional_details' => $details,
            ];

            $lead = Lead::query()->updateOrCreate(
                ['external_source' => 'demo_family_acquisition', 'external_id' => $externalId],
                [
                    'lead_type' => Lead::TYPE_FAMILY,
                    'name' => $name,
                    'email' => str($name)->lower()->replace(' ', '.').'@example.test',
                    'phone' => $phone,
                    'location' => $index % 4 === 0 ? 'Cary, NC' : 'Raleigh, NC',
                    'zip' => $index % 4 === 0 ? '27513' : '27609',
                    'status' => $status,
                    'assigned_admin_id' => $owned ? $sdr->id : null,
                    'priority' => $priority,
                    'source' => $source,
                    'source_detail' => $campaign['name'] ?? 'Manual CRM entry',
                    'contact_role' => $relationship,
                    'submitted_at' => $submittedAt,
                    'first_call_at' => $firstCallAt,
                    'first_connected_at' => $firstConnectedAt,
                    'call_attempt_count' => $attempts,
                    'unanswered_attempt_count' => in_array($status, ['attempting_contact', 'unreachable'], true) ? $attempts : 0,
                    'last_contacted_at' => $lastCallAt,
                    'next_follow_up_at' => $nextFollowUpAt,
                    'converted_at' => $convertedAt,
                    'do_not_contact_at' => $lastOutcome === 'do_not_contact' ? $lastCallAt : null,
                    'closed_reason' => FamilyLeadOutreach::closedReasonForOutcome((string) $lastOutcome, $attempts),
                    'data' => [
                        'source' => $source,
                        'demo' => true,
                        'meta' => $meta,
                        'form_answers' => $formAnswers,
                        'original_submission' => [
                            'full_name' => $name,
                            'phone_number' => $phone,
                            'submitted_at' => $submittedAt->toISOString(),
                            'answers' => $formAnswers,
                            'tracking' => $meta,
                        ],
                        'family_outreach' => [
                            'last_outcome' => $lastOutcome,
                            'last_outcome_label' => $lastOutcome ? FamilyLeadOutreach::outcomeLabel($lastOutcome) : null,
                        ],
                    ],
                ]
            );

            $lead->activities()->delete();
            $lead->activities()->create([
                'actor_user_id' => $campaign ? null : $admin->id,
                'type' => LeadActivity::TYPE_NOTE,
                'summary' => $campaign ? 'Meta lead received' : 'Lead entered manually',
                'body' => $details,
                'occurred_at' => $submittedAt,
                'metadata' => ['source' => $source, 'demo' => true],
            ]);

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $attemptAt = $firstCallAt?->copy()->addDays($attempt - 1) ?? $submittedAt;
                $attemptOutcome = $attempt === $attempts && $lastOutcome
                    ? $lastOutcome
                    : ($attempt % 2 === 0 ? 'voicemail_left' : 'no_answer');

                $lead->activities()->create([
                    'actor_user_id' => $sdr->id,
                    'type' => LeadActivity::TYPE_CALL,
                    'summary' => 'Family call: '.FamilyLeadOutreach::outcomeLabel($attemptOutcome),
                    'body' => $attempt === $attempts ? $details : ($attemptOutcome === 'voicemail_left' ? 'Short voicemail left with callback number.' : 'No answer; retry scheduled.'),
                    'occurred_at' => $attemptAt,
                    'metadata' => [
                        'source' => 'family_calling_console',
                        'family_outcome' => $attemptOutcome,
                        'family_outcome_label' => FamilyLeadOutreach::outcomeLabel($attemptOutcome),
                        'family_attempt_number' => $attempt,
                        'connected' => FamilyLeadOutreach::isConnected($attemptOutcome),
                        'demo' => true,
                    ],
                ]);
            }

            if ($status === 'converted' && $convertedAt) {
                $lead->activities()->create([
                    'actor_user_id' => $admin->id,
                    'type' => LeadActivity::TYPE_CONVERSION,
                    'summary' => 'Care started',
                    'body' => 'Family completed intake and began paid care.',
                    'occurred_at' => $convertedAt,
                    'metadata' => ['source' => 'family_acquisition_demo', 'demo' => true],
                ]);
            }
        }
    }
}
