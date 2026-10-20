<?php

namespace Tests\Feature\Admin;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\FamilyAccount;
use App\Models\FamilyOnboarding;
use App\Models\Lead;
use App\Models\User;
use App\Services\CustomerOnboarding\CustomerOnboardingDirectory;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomerOnboardingDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_lists_existing_accounts_without_onboarding_and_unlinked_family_leads_once(): void
    {
        $account = $this->account('Existing Owner');
        $closed = $this->account('Historical Owner');
        $closed->update(['status' => FamilyAccount::STATUS_CLOSED]);
        $linked = $this->lead(['family_account_id' => $account->id]);
        $prospect = $this->lead(['name' => 'New Prospect']);
        $this->lead(['lead_type' => Lead::TYPE_REFERRAL]);

        $rows = app(CustomerOnboardingDirectory::class)->paginate()->getCollection()->keyBy('key');

        $this->assertCount(3, $rows);
        $this->assertSame($linked->id, $rows['account:'.$account->id]['lead_id']);
        $this->assertNull($rows['account:'.$account->id]['onboarding_id']);
        $this->assertSame('closed', $rows['account:'.$closed->id]['account_status']);
        $this->assertSame('not_created', $rows['lead:'.$prospect->id]['account_status']);
        $this->assertSame('not_applicable', $rows['lead:'.$prospect->id]['payment_status']);
        $this->assertFalse($rows->has('lead:'.$linked->id));
    }

    public function test_payment_snapshots_distinguish_no_customer_unknown_missing_and_ready(): void
    {
        $missing = $this->account('No customer');
        $unknown = $this->account('Existing customer');
        $unknown->update(['stripe_customer_id' => 'cus_unknown']);
        $ready = $this->account('Saved card');
        $ready->forceFill(['stripe_customer_id' => 'cus_ready', 'payment_method_status' => 'ready'])->save();
        $removed = $this->account('Removed card');
        $removed->forceFill(['stripe_customer_id' => 'cus_removed', 'payment_method_status' => 'missing'])->save();
        $legacy = $this->account('Legacy customer');
        $legacy->owner->update(['stripe_customer_id' => 'cus_legacy']);
        $directory = app(CustomerOnboardingDirectory::class);

        $this->assertSame('missing', $directory->find('account:'.$missing->id)['payment_status']);
        $this->assertSame('unknown', $directory->find('account:'.$unknown->id)['payment_status']);
        $this->assertSame('ready', $directory->find('account:'.$ready->id)['payment_status']);
        $this->assertSame('missing', $directory->find('account:'.$removed->id)['payment_status']);
        $this->assertSame('unknown', $directory->find('account:'.$legacy->id)['payment_status']);
        $this->assertSame(2, $directory->paginate(filter: 'payment_unknown')->total());
        $this->assertSame(2, $directory->paginate(filter: 'payment_missing')->total());
    }

    public function test_expired_saved_card_is_missing_in_rows_filters_and_summary(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20));
        $expired = $this->account('Expired card');
        $expired->forceFill([
            'stripe_customer_id' => 'cus_expired', 'payment_method_status' => 'ready',
            'payment_method_summary' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 9, 'exp_year' => 2026],
        ])->save();
        $valid = $this->account('Current month card');
        $valid->forceFill([
            'stripe_customer_id' => 'cus_current', 'payment_method_status' => 'ready',
            'payment_method_summary' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 10, 'exp_year' => 2026],
        ])->save();
        $directory = app(CustomerOnboardingDirectory::class);

        $this->assertSame('missing', $directory->find('account:'.$expired->id)['payment_status']);
        $this->assertSame('ready', $directory->find('account:'.$valid->id)['payment_status']);
        $this->assertSame(1, $directory->paginate(filter: 'payment_missing')->total());
        $this->assertSame(1, $directory->summary()['payment_missing']);
    }

    public function test_closed_accounts_remain_visible_but_do_not_create_payment_follow_up_work(): void
    {
        $this->account('Active missing card');
        $this->account('Active unchecked card')->update(['stripe_customer_id' => 'cus_active']);
        $closedMissing = $this->account('Closed missing card');
        $closedMissing->update(['status' => FamilyAccount::STATUS_CLOSED]);
        $closedUnknown = $this->account('Closed unchecked card');
        $closedUnknown->update(['status' => FamilyAccount::STATUS_CLOSED, 'stripe_customer_id' => 'cus_closed']);
        $directory = app(CustomerOnboardingDirectory::class);

        $this->assertSame(4, $directory->paginate()->total());
        $this->assertSame('closed', $directory->find('account:'.$closedMissing->id)['account_status']);
        $this->assertSame('unknown', $directory->find('account:'.$closedUnknown->id)['payment_status']);
        $this->assertSame(1, $directory->paginate(filter: 'payment_missing')->total());
        $this->assertSame(1, $directory->paginate(filter: 'payment_unknown')->total());
        $this->assertSame(1, $directory->summary()['payment_missing']);
        $this->assertSame(1, $directory->summary()['payment_unknown']);
    }

    public function test_history_does_not_regress_after_cancellation_and_excludes_drafts_and_generated_visits(): void
    {
        $historical = $this->account('Past customer');
        $hired = $this->request($historical, ['status' => CareRequest::STATUS_CANCELLED, 'first_hire_at' => '2026-08-01 10:00:00']);
        $this->request($historical, ['status' => CareRequest::STATUS_OPEN]);
        $draftOnly = $this->account('Draft only');
        $this->request($draftOnly, ['status' => CareRequest::STATUS_DRAFT]);
        $this->request($draftOnly, ['is_system_generated' => true, 'first_hire_at' => '2026-07-01 10:00:00']);
        $published = $this->account('Published only');
        $this->request($published, ['status' => CareRequest::STATUS_EXPIRED]);
        $directory = app(CustomerOnboardingDirectory::class);
        $row = $directory->find('account:'.$historical->id);

        $this->assertTrue($row['request_posted']);
        $this->assertTrue($row['hired']);
        $this->assertSame($hired->first_hire_at->format('Y-m-d H:i:s'), $row['first_hire_at']);
        $this->assertFalse($directory->find('account:'.$draftOnly->id)['request_posted']);
        $this->assertFalse($directory->find('account:'.$draftOnly->id)['hired']);
        $this->assertTrue($directory->find('account:'.$published->id)['request_posted']);
        $this->assertFalse($directory->find('account:'.$published->id)['hired']);
        $this->assertSame(1, $directory->paginate(filter: 'hired')->total());
        $this->assertSame(1, $directory->paginate(filter: 'no_request')->total());
    }

    public function test_older_confirmed_hires_are_recognized_without_inventing_a_hire_timestamp(): void
    {
        $account = $this->account('Older hire');
        $request = $this->request($account, ['status' => CareRequest::STATUS_CANCELLED]);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $application = CareRequestApplication::create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'hired',
        ]);
        CareBooking::create([
            'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_account_id' => $account->id, 'family_user_id' => $account->owner_user_id,
            'caregiver_user_id' => $caregiver->id, 'status' => 'cancelled',
        ]);
        $row = app(CustomerOnboardingDirectory::class)->find('account:'.$account->id);

        $this->assertTrue($row['hired']);
        $this->assertNull($row['first_hire_at']);
    }

    public function test_search_filters_summary_and_pagination_are_database_scoped(): void
    {
        $account = $this->account('Searchable Owner');
        $this->request($account, ['first_hire_at' => now()]);
        $this->lead(['name' => 'Searchable Prospect']);
        for ($i = 0; $i < 5; $i++) {
            $this->account('Other '.$i);
        }
        $directory = app(CustomerOnboardingDirectory::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $page = $directory->paginate('Searchable', 'all', 1);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(2, $page->total());
        $this->assertCount(1, $page->items());
        $this->assertCount(2, $queries);
        $this->assertSame(1, $directory->paginate('Searchable', 'no_account')->total());
        $this->assertSame([
            'total' => 7, 'accounts' => 6, 'no_account' => 1, 'payment_missing' => 6,
            'payment_unknown' => 0, 'request_posted' => 1, 'hired' => 1,
        ], $directory->summary());
    }

    public function test_find_provides_detail_records_and_rejects_linked_lead_keys(): void
    {
        $account = $this->account('Details');
        $lead = $this->lead(['family_account_id' => $account->id]);
        $request = $this->request($account);
        $onboarding = FamilyOnboarding::create([
            'family_account_id' => $account->id, 'initiated_by_user_id' => $account->owner_user_id,
            'source' => 'registration', 'draft' => [], 'request_context' => [],
        ]);
        $row = app(CustomerOnboardingDirectory::class)->find('account:'.$account->id);
        $this->assertTrue($row['account']->is($account));
        $this->assertTrue($row['lead']->is($lead));
        $this->assertTrue($row['onboarding']->is($onboarding));
        $this->assertTrue($row['request']->is($request));

        $this->expectException(HttpException::class);
        app(CustomerOnboardingDirectory::class)->find('lead:'.$lead->id);
    }

    public function test_non_admin_cannot_read_customer_directory(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'sales']));
        $this->expectException(HttpException::class);
        app(CustomerOnboardingDirectory::class)->paginate();
    }

    private function account(string $name): FamilyAccount
    {
        return app(FamilyAccountProvisioner::class)->provisionOwner(User::factory()->create(['role' => 'family', 'name' => $name]));
    }

    private function lead(array $overrides = []): Lead
    {
        return Lead::withoutEvents(fn () => Lead::create([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Family prospect', 'email' => fake()->unique()->safeEmail(),
            'status' => 'new', 'source' => 'manual', ...$overrides,
        ]));
    }

    private function request(FamilyAccount $account, array $overrides = []): CareRequest
    {
        return CareRequest::create([
            'family_account_id' => $account->id, 'family_user_id' => $account->owner_user_id,
            'title' => 'Help at home', 'status' => CareRequest::STATUS_OPEN,
            'address_line1' => '10 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            ...$overrides,
        ]);
    }
}
