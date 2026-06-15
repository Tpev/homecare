import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

function seedCompletedRebookSource(): number {
    const phpCode = String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRecipient;
use App\Models\User;

$family = User::query()->where('email', 'family.e2e@example.com')->firstOrFail();
$caregiver = User::query()->where('email', 'caregiver.ready.e2e@example.com')->firstOrFail();

$request = CareRequest::withoutEvents(function () use ($family) {
    return CareRequest::query()->updateOrCreate(
        ['family_user_id' => $family->id, 'title' => 'E2E completed visit for book again'],
        [
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'additional_info' => 'Completed visit that can be booked again.',
            'scope_of_work' => 'Companionship and meal preparation.',
            'time_expectations' => 'Completed visit used as a rebooking source.',
            'home_access_notes' => 'Ring the doorbell.',
            'preferred_response_hours' => 12,
            'budget_min' => 30,
            'budget_max' => 30,
            'requested_start_at' => now()->subWeek()->setTime(10, 0),
            'requested_end_at' => now()->subWeek()->setTime(12, 0),
            'address_line1' => '123 E2E Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'first_hire_at' => now()->subWeek(),
        ]
    );
});

CareRecipient::query()->updateOrCreate(
    ['care_request_id' => $request->id],
    [
        'recipient_is_requester' => false,
        'full_name' => 'E2E Recipient',
        'relationship_to_family' => 'Mother',
        'care_notes' => 'Needs clear, calm support.',
    ]
);

$application = CareRequestApplication::query()->updateOrCreate(
    ['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id],
    [
        'status' => CareRequestApplication::STATUS_HIRED,
        'proposed_rate' => 30,
        'cover_note' => 'Completed source visit.',
    ]
);

CareBooking::query()->updateOrCreate(
    ['care_request_id' => $request->id],
    [
        'care_request_application_id' => $application->id,
        'family_user_id' => $family->id,
        'caregiver_user_id' => $caregiver->id,
        'status' => CareBooking::STATUS_REVIEWED,
        'scheduled_start_at' => now()->subWeek()->setTime(10, 0),
        'scheduled_end_at' => now()->subWeek()->setTime(12, 0),
        'completed_at' => now()->subWeek()->setTime(12, 0),
        'timesheet_submitted_at' => now()->subWeek()->setTime(12, 5),
        'worked_minutes' => 120,
        'family_confirmed_at' => now()->subWeek()->setTime(12, 15),
    ]
);

echo $request->id;
`;

    const output = execFileSync('php', ['-r', phpCode], {
        cwd: process.cwd(),
        env: {
            ...process.env,
            APP_ENV: 'playwright',
            APP_URL: 'http://127.0.0.1:8010',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: path.join(process.cwd(), 'database', 'playwright.sqlite'),
            CACHE_STORE: 'file',
            SESSION_DRIVER: 'file',
            QUEUE_CONNECTION: 'sync',
            MAIL_MAILER: 'array',
            DIDIT_BYPASS: 'true',
        },
    }).toString().trim();

    return Number(output);
}

test.describe('Book Again Flow', () => {
    test('family can create one-time rebook invite and caregiver sees it on desktop and mobile', async ({ page }) => {
        const sourceRequestId = seedCompletedRebookSource();

        await page.setViewportSize({ width: 1440, height: 1000 });
        await loginAs(page, 'family');
        await page.goto(`/family/requests/${sourceRequestId}/book-again`);

        await expect(page.getByRole('heading', { name: 'Book E2E without starting over.' })).toBeVisible();
        await expect(page.getByText('One more visit', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Starting day')).toBeVisible();
        await expect(page.getByLabel('Starting time')).toBeVisible();
        await expect(page.getByLabel('Duration')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Set up weekly care' })).toBeVisible();

        await page.getByRole('button', { name: 'Send one-time invite' }).click();
        await expect(page.getByText('One-time invite sent to E2E Ready Caregiver.')).toBeVisible();

        await loginAs(page, 'caregiverReady');
        await page.goto('/caregiver/work-inbox');
        await expect(page.getByText('One-time care for E2E Recipient').first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Accept invite' }).first()).toBeVisible();

        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'family');
        await page.goto(`/family/requests/${sourceRequestId}/book-again`);

        await expect(page.getByRole('heading', { name: 'Book E2E without starting over.' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Send one-time invite' })).toBeVisible();

        const hasHorizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
        expect(hasHorizontalOverflow).toBe(false);
    });
});
