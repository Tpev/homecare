<?php

/**
 * Real MySQL, independent-process care-hours verification.
 * Creates a new care_hours_concurrency_* schema; never selects the application database.
 * Run: php tests/concurrency/care-hours.php
 */

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CareHourAccount;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CareHourSettlement;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\CareHours\PrepaidBookingSettlementService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

function enableTestFeature(): void
{
    // In-memory configuration for this disposable test process only.
    config(['care_hours.enabled' => true, 'care_hours.pilot_only' => false, 'care_hours.enrollment_enabled' => true,
        'care_hours.sales_enabled' => true, 'care_hours.gifts_enabled' => true, 'care_hours.welcome_enabled' => true]);
}

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function connectionSettings(): array
{
    if ($port = getenv('CARE_HOURS_CONCURRENCY_PORT')) {
        check(ctype_digit($port) && (int) $port > 1024 && (int) $port < 65536, 'Invalid isolated MySQL port.');

        return ['host' => '127.0.0.1', 'port' => $port, 'username' => 'root', 'password' => ''];
    }
    $values = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/.env'));
    $host = $values['DB_HOST'] ?? '127.0.0.1';
    check(($values['DB_CONNECTION'] ?? '') === 'mysql' && in_array($host, ['127.0.0.1', 'localhost'], true),
        'This runner requires the configured local MySQL service; remote servers are refused.');

    return ['host' => $host, 'port' => $values['DB_PORT'] ?? '3306',
        'username' => $values['DB_USERNAME'] ?? '', 'password' => $values['DB_PASSWORD'] ?? ''];
}

function serverConnection(array $settings): PDO
{
    return new PDO('mysql:host='.$settings['host'].';port='.$settings['port'], $settings['username'], $settings['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function bootIsolated(string $database, string $directory, array $settings): void
{
    check((bool) preg_match('/^care_hours_concurrency_[a-z0-9_]+$/', $database), 'Refusing a non-disposable database name.');
    $environment = [
        'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(hash('sha256', 'care-hours-concurrency-fixture-only', true)),
        'APP_CONFIG_CACHE' => $directory.'/config.php', 'APP_PACKAGES_CACHE' => $directory.'/packages.php',
        'APP_SERVICES_CACHE' => $directory.'/services.php', 'APP_ROUTES_CACHE' => $directory.'/routes.php',
        'VIEW_COMPILED_PATH' => $directory.'/views', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
        'DB_HOST' => $settings['host'], 'DB_PORT' => $settings['port'], 'DB_USERNAME' => $settings['username'],
        'DB_PASSWORD' => $settings['password'], 'DB_URL' => '', 'DATABASE_URL' => '',
        'CACHE_STORE' => 'database', 'CACHE_PREFIX' => $database, 'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null',
        'STRIPE_BYPASS' => 'true', 'STRIPE_SECRET' => '', 'STRIPE_KEY' => '',
        'STRIPE_BYPASS_PROCESSING_FEE_PERCENT' => '0', 'STRIPE_BYPASS_PROCESSING_FEE_FIXED_CENTS' => '0',
        'NIGHTWATCH_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false', 'PULSE_ENABLED' => 'false',
        'BCRYPT_ROUNDS' => '4', 'CARE_HOURS_ENABLED' => 'false',
    ];
    foreach ($environment as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    // The disposable runner never reads the application's normal environment.
    $app->useEnvironmentPath($directory);
    $app->loadEnvironmentFrom('.env');
    $app->addAbsoluteCachePathPrefix(dirname(__DIR__, 2));
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    check(config('database.default') === 'mysql' && config('database.connections.mysql.database') === $database,
        'Application configuration escaped the isolated database.');
    check(DB::selectOne('SELECT DATABASE() AS name')->name === $database, 'Actual connection is not the isolated database.');
    config(['services.stripe.bypass' => true, 'services.stripe.secret' => null,
        'care_hours.enabled' => false, 'marketplace.prelaunch_enabled' => false, 'mail.default' => 'array',
        'logging.default' => 'single', 'logging.channels.single.path' => $directory.'/application.log']);
    Notification::fake();
    Illuminate\Support\Facades\Http::preventStrayRequests();
    Stripe\ApiRequestor::setHttpClient(new class implements Stripe\HttpClient\ClientInterface
    {
        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            throw new RuntimeException('Provider I/O is forbidden in concurrency tests.');
        }
    });
}

function seedBooking(?User $family = null): array
{
    $family ??= User::factory()->create(['role' => 'family']);
    $caregiver = User::factory()->create(['role' => 'caregiver']);
    CaregiverProfile::create(['user_id' => $caregiver->id, 'status' => 'active', 'screening_initial_approved_at' => now(),
        'identity_verified_at' => now(), 'identity_verification_status' => 'approved', 'platform_hourly_rate' => 27]);
    $request = CareRequest::create(['family_user_id' => $family->id, 'title' => 'Concurrency fixture', 'status' => 'filled',
        'request_type' => 'one_time', 'requested_start_at' => now()->addDay()->setTime(9, 0),
        'requested_end_at' => now()->addDay()->setTime(11, 0), 'address_line1' => 'Test address', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
    $recipient = $request->recipient()->create(['full_name' => 'Concurrency recipient', 'relationship_to_family' => 'Parent']);
    $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
        'status' => 'hired', 'proposed_rate' => 27]);
    $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
        'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
        'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);

    return [$family, $recipient, $booking];
}

function seedAccount(): array
{
    [$family, $recipient, $booking] = seedBooking();
    $account = app(CareHourIdentityService::class)->enroll($recipient, $family);

    return [$account, $family, $booking];
}

function credit(CareHourAccount $account, User $family, int $minutes): CareHourPurchase
{
    $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
        'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
        'buyer_user_id' => $family->id, 'buyer_email' => $family->email, 'buyer_name' => $family->name,
        'paid_minutes' => $minutes, 'bonus_minutes' => 0, 'total_minutes' => $minutes, 'amount_cents' => intdiv($minutes * 3100, 60),
        'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'terms_version' => 'concurrency-test',
        'quote' => [], 'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
    app(CareHourLedgerService::class)->issuePurchase($purchase);

    return $purchase;
}

function migrationRehearsal(string $directory): array
{
    $files = glob(dirname(__DIR__, 2).'/database/migrations/*.php');
    sort($files);
    $new = array_values(array_filter($files, fn ($file) => str_contains(basename($file), 'care_hour')));
    $baseline = array_values(array_diff($files, $new));
    check(Artisan::call('migrate', ['--path' => $baseline, '--realpath' => true, '--force' => true]) === 0, 'Baseline migration failed.');
    file_put_contents($directory.'/migration-baseline.log', Artisan::output());
    [, , $booking] = seedBooking();
    $payment = CareBookingPayment::create(['care_booking_id' => $booking->id, 'status' => 'authorized', 'currency' => 'usd',
        'family_user_id' => $booking->family_user_id, 'caregiver_user_id' => $booking->caregiver_user_id,
        'amount_authorized_cents' => 6200, 'stripe_payment_intent_id' => 'pi_concurrency_legacy_fixture']);
    $before = hash('sha256', json_encode([$booking->fresh()->getAttributes(), $payment->fresh()->getAttributes()]));
    check(Artisan::call('migrate', ['--path' => $new, '--realpath' => true, '--force' => true]) === 0, 'Additive migration failed.');
    file_put_contents($directory.'/migration-additive.log', Artisan::output());
    check($before === hash('sha256', json_encode([$booking->fresh()->getAttributes(), $payment->fresh()->getAttributes()])),
        'Additive migrations changed an existing card booking/payment.');
    check(Artisan::call('migrate', ['--force' => true]) === 0, 'Repeated migration failed.');
    file_put_contents($directory.'/migration-repeat.log', Artisan::output());
    check(! app(PrepaidBookingFundingService::class)->isPrepaid($booking), 'Existing card booking acquired prepaid mode.');

    return ['baseline_count' => count($baseline), 'additive_count' => count($new),
        'existing_card_row_hash_unchanged' => true, 'repeat_succeeded' => true, 'production_exposure_enabled' => false];
}

function paidGift(): array
{
    Notification::fake();
    $gifts = app(CareHourGiftService::class);
    $verification = $gifts->requestVerification(Str::uuid().'@example.test', 'Concurrency gift buyer');
    $sent = Notification::sent(new \Illuminate\Notifications\AnonymousNotifiable, \App\Notifications\CareHourVerificationNotification::class)->last();
    check($sent !== null, 'The test verification notification was not delivered.');
    $token = $gifts->verify($verification->id, $sent->code);
    $purchase = $gifts->start($token, 10, $gifts->quote(10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
    $purchase = app(CareHourPurchaseService::class)->refresh($purchase);

    return [$purchase, $gifts->codeForBuyer($purchase, null, $token)];
}

function runJob(array $job): mixed
{
    $account = isset($job['account']) ? CareHourAccount::findOrFail($job['account']) : null;
    $actor = isset($job['actor']) ? User::findOrFail($job['actor']) : null;
    $booking = isset($job['booking']) ? CareBooking::findOrFail($job['booking']) : null;

    return match ($job['action']) {
        'purchase' => app(CareHourPurchaseService::class)->start($account, 10, $actor, $job['quote'],
            $job['request'], url('/ok'), url('/cancel'))->id,
        'refresh' => app(CareHourPurchaseService::class)->refresh(CareHourPurchase::findOrFail($job['purchase']))->id,
        'reserve' => app(CareHourLedgerService::class)->reserve($account, $booking, $job['minutes']),
        'settle' => app(PrepaidBookingSettlementService::class)->settle($booking)->id,
        'reconcile' => app(PrepaidBookingSettlementService::class)->reconcile(CareHourSettlement::findOrFail($job['settlement']))->id,
        'release' => (function () use ($booking) {
            app(PrepaidBookingFundingService::class)->release($booking);

            return true;
        })(),
        'cancel_funding' => DB::transaction(function () use ($booking) {
            $current = CareBooking::lockForUpdate()->findOrFail($booking->id);
            $current->update(['status' => CareBooking::STATUS_CANCELLED, 'cancelled_at' => now()]);
            app(PrepaidBookingFundingService::class)->release($current);

            return true;
        }, 3),
        'fail_shortfall' => (function () use ($job) {
            app(PrepaidBookingFundingService::class)->failShortfall($job['operation']);

            return true;
        })(),
        // A stale reservation-release callback must never erase approved usage.
        'release_reservation' => (function () use ($booking) {
            app(CareHourLedgerService::class)->release($booking);

            return true;
        })(),
        'claim' => app(CareHourGiftService::class)->claim($job['code'], $account, $actor, $job['quote'])->id,
        'self_claim' => (function () use ($job, $actor) {
            $gifts = app(CareHourGiftService::class);
            $account = $gifts->resolveSelfClaimAccount($job['code'], $actor);
            $quote = $gifts->previewForClaim($job['code'], $account, $actor);
            $purchase = $gifts->claim($job['code'], $account, $actor, $quote['quote_key']);

            return ['account_id' => $account->id, 'purchase_id' => $purchase->id];
        })(),
        'cache_lock' => Cache::lock($job['key'], 10)->block(5, function () {
            $entered = microtime(true);
            usleep(350000);

            return ['entered' => $entered, 'left' => microtime(true)];
        }),
        default => throw new RuntimeException('Unknown concurrency job.'),
    };
}

function worker(string $database, string $directory, string $jobId): never
{
    $expected = realpath(dirname(__DIR__, 2).'/tmp/care-hours-concurrency/'.$database);
    check($expected !== false && realpath($directory) === $expected, 'Worker directory is outside its disposable run.');
    check((bool) preg_match('/^[a-z0-9_-]+$/', $jobId), 'Invalid worker job identifier.');
    bootIsolated($database, $directory, connectionSettings());
    enableTestFeature();
    $job = json_decode(file_get_contents($directory.'/'.$jobId.'.job.json'), true, flags: JSON_THROW_ON_ERROR);
    $held = false;
    $lockTimes = [];
    $familyLockTimes = [];
    $bookingLockTimes = [];
    $bookingLockAcquiredAt = [];
    $orderedRelease = in_array($job['action'], ['cancel_funding', 'fail_shortfall'], true);
    $contentionTable = $orderedRelease ? 'care_bookings' : ($job['action'] === 'self_claim' ? 'family_accounts' : 'care_hour_accounts');
    DB::listen(function ($query) use (&$held, &$lockTimes, &$familyLockTimes, &$bookingLockTimes, &$bookingLockAcquiredAt, $contentionTable, $directory, $job) {
        if (str_contains(strtolower($query->sql), 'for update')) {
            $lockTimes[] = round($query->time, 2);
            if (str_contains($query->sql, 'family_accounts')) {
                $familyLockTimes[] = round($query->time, 2);
            }
            if (str_contains($query->sql, 'care_bookings')) {
                $bookingLockTimes[] = round($query->time, 2);
                $bookingLockAcquiredAt[] = microtime(true);
            }
            if (! $held && str_contains($query->sql, $contentionTable)) {
                $held = true;
                if ($job['first_booking_lock'] ?? false) {
                    file_put_contents($directory.'/'.$job['race'].'.booking-held', (string) getmypid());
                    $deadline = microtime(true) + 10;
                    while (! is_file($directory.'/'.$job['race'].'.competitor-starting')) {
                        check(microtime(true) < $deadline, 'Ordered lock competitor did not start.');
                        usleep(10000);
                    }
                }
                // Deterministically keep the real row lock long enough for its competitor to wait.
                usleep(300000);
            }
        }
    });
    file_put_contents($directory.'/'.$jobId.'.ready', (string) getmypid());
    $deadline = microtime(true) + 60;
    while (! is_file($directory.'/'.$job['race'].'.go')) {
        check(microtime(true) < $deadline, 'Worker start barrier timed out.');
        usleep(10000);
    }
    $result = ['pid' => getmypid(), 'started_at' => microtime(true)];
    try {
        if ($job['wait_for_booking_lock'] ?? false) {
            $deadline = microtime(true) + 10;
            while (! is_file($directory.'/'.$job['race'].'.booking-held')) {
                check(microtime(true) < $deadline, 'Ordered booking lock was not acquired.');
                usleep(10000);
            }
            file_put_contents($directory.'/'.$job['race'].'.competitor-starting', (string) getmypid());
        }
        $result['value'] = runJob($job);
        $result['outcome'] = 'success';
    } catch (ValidationException|\App\Exceptions\Payments\PaymentException $error) {
        $result['outcome'] = 'rejected';
        $result['reason'] = $error->getMessage();
    } catch (Throwable $error) {
        $result['outcome'] = 'error';
        $result['reason'] = $error::class.': '.$error->getMessage();
        $result['trace'] = $error->getTraceAsString();
    }
    $result['finished_at'] = microtime(true);
    $result['locking_query_ms'] = $lockTimes;
    $result['family_locking_query_ms'] = $familyLockTimes;
    $result['booking_locking_query_ms'] = $bookingLockTimes;
    $result['booking_lock_acquired_at'] = $bookingLockAcquiredAt;
    file_put_contents($directory.'/'.$jobId.'.result.json', json_encode($result, JSON_PRETTY_PRINT));
    exit($result['outcome'] === 'error' ? 1 : 0);
}

function race(string $database, string $directory, string $name, array $jobs): array
{
    $name .= '-'.bin2hex(random_bytes(3));
    $processes = [];
    foreach ($jobs as $index => $job) {
        $id = $name.'-'.$index;
        file_put_contents($directory.'/'.$id.'.job.json', json_encode($job + ['race' => $name], JSON_PRETTY_PRINT));
        $process = new Process([PHP_BINARY, __FILE__, '--worker', $database, $directory, $id], dirname(__DIR__, 2));
        $process->setTimeout(120);
        $process->start();
        $processes[$id] = $process;
    }
    $deadline = microtime(true) + 90;
    do {
        $ready = 0;
        foreach ($processes as $id => $process) {
            if (is_file($directory.'/'.$id.'.ready')) {
                $ready++;
            } elseif (! $process->isRunning()) {
                throw new RuntimeException('Worker failed before barrier: '.$process->getErrorOutput().$process->getOutput());
            }
        }
        check(microtime(true) < $deadline, 'Parent start barrier timed out.');
        usleep(10000);
    } while ($ready !== count($jobs));
    file_put_contents($directory.'/'.$name.'.go', 'go');
    $results = [];
    foreach ($processes as $id => $process) {
        $process->wait();
        check(is_file($directory.'/'.$id.'.result.json'), 'Missing result from '.$id.': '.$process->getErrorOutput());
        $result = json_decode(file_get_contents($directory.'/'.$id.'.result.json'), true, flags: JSON_THROW_ON_ERROR);
        $results[] = $result;
        check($result['outcome'] !== 'error', $name.' worker failed: '.($result['reason'] ?? ''));
    }
    check(count(array_unique(array_column($results, 'pid'))) === count($jobs), 'Race did not use independent processes.');
    check(max(array_column($results, 'started_at')) < min(array_column($results, 'finished_at')), 'Worker execution did not overlap.');
    file_put_contents($directory.'/'.$name.'.json', json_encode($results, JSON_PRETTY_PRINT));

    return $results;
}

function assertConservation(CareHourAccount $account): void
{
    $balance = $account->balance();
    $lots = \App\Models\CareHourLot::whereIn('care_hour_account_id', $account->familySourceIds())->get();
    check(array_sum($balance) === (int) $lots->where('status', '!=', 'reversed')->sum('total_minutes'), 'Minute conservation failed.');
    foreach ($lots as $lot) {
        $end = 0;
        foreach ($lot->allocations()->whereIn('status', ['reserved', 'used'])->orderBy('start_minute')->get() as $allocation) {
            check($allocation->start_minute >= $end, 'Two active allocations overlap.');
            $end = $allocation->start_minute + $allocation->minutes;
            check($end <= $lot->total_minutes, 'Allocation extends beyond its purchase.');
        }
    }
}

function selfClaimCheck(string $database, string $directory): array
{
    enableTestFeature();
    $actor = User::factory()->create(['role' => 'family', 'name' => 'Concurrency self recipient']);
    // Gift registration already provisions the private family, before identity selection.
    $family = app(FamilyAccountContext::class)->account($actor);
    $gifts = app(CareHourGiftService::class);
    [$firstPurchase, $firstCode] = paidGift();
    [$secondPurchase, $secondCode] = paidGift();
    check(CareRecipientProfile::where('family_account_id', $family->id)->count() === 0
        && CareHourAccount::where('family_account_id', $family->id)->count() === 0, 'Self-claim fixture already has an identity.');
    $job = ['action' => 'self_claim', 'actor' => $actor->id];
    $results = race($database, $directory, 'gift-self-two-codes', [$job + ['code' => $firstCode], $job + ['code' => $secondCode]]);
    $familyLockWait = max(array_merge(...array_column($results, 'family_locking_query_ms')));
    check($familyLockWait >= 200, 'Self-claim workers did not contend on the real family lock.');
    check(count(array_filter($results, fn ($result) => $result['outcome'] === 'success')) >= 1, 'Neither concurrent self claim succeeded.');
    check(CareRecipientProfile::where('family_account_id', $family->id)->count() === 0
        && CareHourAccount::where('family_account_id', $family->id)->count() === 1, 'Concurrent gift claims must create one family wallet without a care profile.');
    $account = CareHourAccount::where('family_account_id', $family->id)->sole();
    check($account->care_recipient_profile_id === null, 'Receiving credit must not create a recipient identity.');
    $requoted = 0;
    foreach ([[$firstPurchase, $firstCode], [$secondPurchase, $secondCode]] as $index => [$purchase, $code]) {
        if ($results[$index]['outcome'] === 'rejected') {
            check(str_contains($results[$index]['reason'], 'welcome offer changed'), 'Self claim was rejected for an unexpected reason.');
            $quote = $gifts->previewForClaim($code, $account, $actor);
            check($quote['bonus_minutes'] === 0, 'The stale welcome quote did not resolve to paid hours only.');
            $gifts->claim($code, $account, $actor, $quote['quote_key']);
            $requoted++;
        }
        $purchase->refresh();
        check($purchase->care_hour_account_id === $account->id && $purchase->status === 'issued', 'Gift was not assigned to the single self account.');
        // Exact repeated confirmation must not duplicate either paid hours or the welcome award.
        check($gifts->claim($code, $account, $actor, $purchase->gift->claim_quote_key)->id === $purchase->id, 'Self-claim replay changed the purchase.');
    }
    check($account->balance()['available_minutes'] === 1260 && $account->lots()->count() === 2
        && (int) $account->lots()->sum('bonus_minutes') === 60, 'Two distinct gifts did not conserve 1200 paid plus 60 welcome minutes.');
    check(DB::table('care_hour_welcome_claims')->where('care_hour_account_id', $account->id)->where('status', 'awarded')->count() === 1,
        'Concurrent self claims did not award exactly one welcome.');
    check(CareRequest::where('family_account_id', $family->id)->count() === 0
        && CareBooking::where('family_account_id', $family->id)->count() === 0, 'Self claim created care or booking records.');
    assertConservation($account);

    return ['independent_pids' => array_column($results, 'pid'), 'overlapping_workers' => true,
        'family_lock_wait_ms' => $familyLockWait, 'profiles' => 0, 'accounts' => 1, 'distinct_paid_gifts' => 2,
        'lots' => 2, 'welcome_awards' => 1, 'paid_minutes' => 1200, 'bonus_minutes' => 60,
        'available_minutes' => 1260, 'no_recipient_required' => true, 'confirmation_replays_safe' => true, 'stale_quote_reviews' => $requoted];
}

function fundingReleaseCheck(string $database, string $directory): array
{
    enableTestFeature();
    $report = [];
    $cardRowsBefore = hash('sha256', CareBookingPayment::orderBy('id')->get()->toJson());
    foreach (['cancel_funding', 'fail_shortfall'] as $firstAction) {
        [$wallet, $owner, $booking] = seedAccount();
        $source = CareHourAccount::create(['family_account_id' => $wallet->family_account_id,
            'display_name' => 'Historical second care balance', 'currency' => 'usd']);
        credit($wallet, $owner, 35);
        credit($source, $owner, 25);
        $service = app(PrepaidBookingFundingService::class);
        $service->selectForBooking($booking, $wallet, $owner);
        $funding = $service->prepare($booking);
        $operation = CareHourPaymentOperation::create(['public_id' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(),
            'care_hour_booking_funding_id' => $funding->id, 'kind' => 'shortfall', 'status' => 'checkout',
            'amount_cents' => $funding->shortfall_cents, 'currency' => 'usd', 'metadata' => ['funding_revision' => $funding->revision]]);
        check($funding->reserved_minutes === 60 && $funding->shortfall_minutes === 60, 'Expected a pooled mixed-funding fixture.');
        $ranges = DB::table('care_hour_allocations')->where('care_booking_id', $booking->id)->orderBy('id')->get();
        check($ranges->count() === 2 && $ranges->pluck('care_hour_account_id')->all() === [$wallet->id, $source->id], 'Both historical sources must fund the visit.');
        $common = ['booking' => $booking->id, 'operation' => $operation->public_id];
        $secondAction = $firstAction === 'cancel_funding' ? 'fail_shortfall' : 'cancel_funding';
        $results = race($database, $directory, 'pooled-release-'.$firstAction, [
            $common + ['action' => $firstAction, 'first_booking_lock' => true],
            $common + ['action' => $secondAction, 'wait_for_booking_lock' => true],
        ]);
        check(count(array_filter($results, fn ($result) => $result['outcome'] === 'success')) === 2, 'Both concurrent callbacks must succeed.');
        check($results[0]['booking_lock_acquired_at'][0] < $results[1]['booking_lock_acquired_at'][0], 'Callbacks acquired their locks in the wrong order.');
        $wait = max($results[1]['booking_locking_query_ms']);
        check($wait >= 200, 'The competing callback did not wait on a real MySQL booking lock.');
        foreach ([$firstAction, $secondAction] as $index => $action) {
            check(count($results[$index]['booking_locking_query_ms']) === ($action === 'cancel_funding' ? 2 : 1), 'A transaction retried or reacquired its initial lock unexpectedly.');
        }
        $funding->refresh();
        check($funding->status === 'released' && $funding->reserved_minutes === 0, 'Cancellation must leave funding released.');
        check($booking->fresh()->status === CareBooking::STATUS_CANCELLED && $operation->fresh()->status === 'failed', 'Final callback states are incorrect.');
        check($wallet->balance() === ['available_minutes' => 60, 'reserved_minutes' => 0, 'used_minutes' => 0, 'held_minutes' => 0], 'Pooled minutes were not restored exactly once.');
        $released = DB::table('care_hour_allocations')->where('care_booking_id', $booking->id)->orderBy('id')->get();
        foreach ($ranges as $index => $range) {
            $current = $released[$index];
            check($current->status === 'released' && $current->care_hour_account_id === $range->care_hour_account_id
                && $current->care_hour_lot_id === $range->care_hour_lot_id && $current->start_minute === $range->start_minute
                && $current->minutes === $range->minutes, 'Release changed a historical source range.');
        }
        $releaseEntries = app(CareHourLedgerService::class)->history($wallet)->where('type', 'release')->reorder('care_hour_account_id')->get();
        check($releaseEntries->count() === 2 && $releaseEntries->pluck('care_hour_account_id')->all() === [$wallet->id, $source->id]
            && $releaseEntries->sum('minutes') === 60, 'Source release ledger was duplicated or repointed.');
        $beforeReplay = hash('sha256', json_encode([\Illuminate\Support\Arr::except($funding->fresh()->getAttributes(), ['updated_at']), $released->toArray(),
            app(CareHourLedgerService::class)->history($wallet)->get()->toArray()]));
        $service->failShortfall($operation->public_id);
        $service->release($booking->fresh());
        $service->failShortfall($operation->public_id);
        check($beforeReplay === hash('sha256', json_encode([\Illuminate\Support\Arr::except($funding->fresh()->getAttributes(), ['updated_at']),
            DB::table('care_hour_allocations')->where('care_booking_id', $booking->id)->orderBy('id')->get()->toArray(),
            app(CareHourLedgerService::class)->history($wallet)->get()->toArray()])), 'A late callback changed released credit or funding.');
        assertConservation($wallet);
        $report[$firstAction.'_first'] = ['independent_pids' => array_column($results, 'pid'), 'overlapping_workers' => true,
            'booking_lock_wait_ms' => $wait, 'transaction_retries' => 0, 'source_accounts' => 2,
            'restored_minutes' => 60, 'source_release_entries' => 2, 'funding_status' => 'released',
            'original_ranges_preserved' => true, 'late_replays_unchanged' => true];
    }
    check($cardRowsBefore === hash('sha256', CareBookingPayment::orderBy('id')->get()->toJson()), 'Existing card payment rows changed.');
    $report['existing_card_rows_unchanged'] = true;

    return $report;
}

function concurrencyChecks(string $database, string $directory): array
{
    enableTestFeature();
    $report = [];
    $purchases = app(CareHourPurchaseService::class);
    $ledger = app(CareHourLedgerService::class);
    $funding = app(PrepaidBookingFundingService::class);
    $gifts = app(CareHourGiftService::class);
    $lockJob = ['action' => 'cache_lock', 'key' => 'concurrency-shared-cache'];
    $locks = race($database, $directory, 'shared-cache-lock', [$lockJob, $lockJob]);
    $intervals = array_column($locks, 'value');
    usort($intervals, fn ($a, $b) => $a['entered'] <=> $b['entered']);
    check($intervals[0]['left'] <= $intervals[1]['entered'], 'Shared cache lock allowed overlapping critical sections.');
    $report['shared_cache_lock'] = ['store' => config('cache.default'), 'independent_pids' => array_column($locks, 'pid'), 'critical_sections_do_not_overlap' => true];

    [$account, $family] = seedAccount();
    $job = ['action' => 'purchase', 'account' => $account->id, 'actor' => $family->id, 'quote' => $purchases->quote($account, 10)['quote_key']];
    $welcome = race($database, $directory, 'welcome-purchase', [$job + ['request' => (string) Str::uuid()], $job + ['request' => (string) Str::uuid()]]);
    check(count(array_filter($welcome, fn ($r) => $r['outcome'] === 'success')) === 1, 'Two purchases received the same welcome quote.');
    $purchase = $account->purchases()->firstOrFail();
    $refresh = ['action' => 'refresh', 'purchase' => $purchase->id];
    race($database, $directory, 'purchase-paid-replay', [$refresh, $refresh]);
    check($account->lots()->count() === 1 && $account->balance()['available_minutes'] === 660, 'Paid replay duplicated credits.');
    check(DB::table('care_hour_welcome_claims')->where('care_hour_account_id', $account->id)->count() === 1, 'Welcome eligibility duplicated.');
    $report['welcome_and_paid_replay'] = ['credited_minutes' => 660, 'welcome_claims' => 1, 'lots' => 1];

    [$account, $family, $firstBooking] = seedAccount();
    [, $otherRecipient, $secondBooking] = seedBooking($family);
    app(CareHourIdentityService::class)->linkRecipient($account, $otherRecipient, $family);
    credit($account, $family, 180);
    $reserve = ['action' => 'reserve', 'account' => $account->id, 'minutes' => 120];
    $reservations = race($database, $directory, 'competing-reservations', [$reserve + ['booking' => $firstBooking->id], $reserve + ['booking' => $secondBooking->id]]);
    $minutes = array_column($reservations, 'value');
    sort($minutes);
    check($minutes === [60, 120] && $account->balance()['reserved_minutes'] === 180, 'Competing visits overspent the same minutes.');
    assertConservation($account);
    $report['competing_reservations'] = ['reserved_per_visit' => $minutes, 'overlapping_allocations' => false];

    // Different historical recipient accounts must contend on the same family wallet.
    [$account, $family, $firstBooking] = seedAccount();
    [, , $secondBooking] = seedBooking($family);
    $legacy = CareHourAccount::create(['family_account_id' => $account->family_account_id,
        'display_name' => 'Historical second recipient', 'currency' => 'usd', 'active' => true]);
    credit($account, $family, 90);
    credit($legacy, $family, 90);
    $reservations = race($database, $directory, 'pooled-competing-reservations', [
        ['action' => 'reserve', 'account' => $account->id, 'booking' => $firstBooking->id, 'minutes' => 120],
        ['action' => 'reserve', 'account' => $legacy->id, 'booking' => $secondBooking->id, 'minutes' => 120],
    ]);
    $minutes = array_column($reservations, 'value');
    sort($minutes);
    check($minutes === [60, 120] && $account->balance()['reserved_minutes'] === 180, 'Pooled sources overspent their shared hours.');
    check(DB::table('care_hour_allocations as a')->join('care_hour_lots as l', 'l.id', '=', 'a.care_hour_lot_id')
        ->whereIn('a.care_booking_id', [$firstBooking->id, $secondBooking->id])
        ->whereColumn('a.care_hour_account_id', '!=', 'l.care_hour_account_id')->count() === 0, 'An allocation lost its historical source identity.');
    assertConservation($account);
    $ledger->release($firstBooking);
    $ledger->release($secondBooking);
    check($account->balance()['available_minutes'] === 180, 'Pooled cancellation did not restore the exact hours.');
    $report['pooled_competing_reservations'] = ['reserved_per_visit' => $minutes, 'source_accounts' => 2,
        'source_identity_preserved' => true, 'restored_minutes' => 180];

    $firstQuote = $purchases->quote($account, 10);
    $otherQuote = $purchases->quote($legacy, 10);
    $welcome = race($database, $directory, 'pooled-family-welcome', [
        ['action' => 'purchase', 'account' => $account->id, 'actor' => $family->id, 'quote' => $firstQuote['quote_key'], 'request' => (string) Str::uuid()],
        ['action' => 'purchase', 'account' => $legacy->id, 'actor' => $family->id, 'quote' => $otherQuote['quote_key'], 'request' => (string) Str::uuid()],
    ]);
    check(count(array_filter($welcome, fn ($r) => $r['outcome'] === 'success')) === 1, 'Different historical sources both won a family welcome bonus.');
    $report['pooled_family_welcome'] = ['successful_welcome_purchases' => 1, 'source_accounts' => 2];

    [$account, $family, $booking] = seedAccount();
    credit($account, $family, 180);
    $funding->selectForBooking($booking, $account, $family);
    $funding->prepare($booking);
    $booking->update(['status' => CareBooking::STATUS_REVIEWED, 'worked_minutes' => 120, 'family_confirmed_at' => now()]);
    $settle = ['action' => 'settle', 'booking' => $booking->id];
    race($database, $directory, 'approval-stale-release', [$settle, ['action' => 'release_reservation', 'booking' => $booking->id]]);
    race($database, $directory, 'approval-replay', [$settle, $settle]);
    check(CareHourSettlement::where('care_booking_id', $booking->id)->count() === 1, 'Approval created duplicate settlements.');
    check($account->balance()['used_minutes'] === 120 && $account->balance()['available_minutes'] === 60, 'Approval replay changed the balance.');
    $settlement = CareHourSettlement::where('care_booking_id', $booking->id)->firstOrFail();
    $reconcile = ['action' => 'reconcile', 'settlement' => $settlement->id];
    race($database, $directory, 'settlement-reconciliation', [$reconcile, $reconcile]);
    check($account->balance()['used_minutes'] === 120, 'Stale release erased approved usage.');
    check((int) $account->ledgerEntries()->where('type', 'usage')->sum('minutes') === 120, 'Approval replay duplicated ledger usage.');
    assertConservation($account);
    $report['approval_reconciliation_and_stale_release'] = ['settlements' => 1, 'used_minutes' => 120, 'revision' => $settlement->fresh()->revision];

    [$account, $family, $booking] = seedAccount();
    credit($account, $family, 120);
    $funding->selectForBooking($booking, $account, $family);
    $funding->prepare($booking);
    $booking->update(['status' => CareBooking::STATUS_CANCELLED, 'cancelled_at' => now()]);
    $cancel = ['action' => 'release', 'booking' => $booking->id];
    race($database, $directory, 'cancellation-replay', [$cancel, $cancel]);
    check($account->balance()['available_minutes'] === 120 && $account->balance()['reserved_minutes'] === 0, 'Cancellation did not restore exactly the reserved hours.');
    assertConservation($account);
    $report['cancellation_replay'] = ['restored_minutes' => 120, 'settlements' => CareHourSettlement::where('care_booking_id', $booking->id)->count()];
    $report['pooled_release_failure_callbacks'] = fundingReleaseCheck($database, $directory);

    [$firstAccount, $firstOwner] = seedAccount();
    [$secondAccount, $secondOwner] = seedAccount();
    [$giftPurchase, $code] = paidGift();
    $firstClaim = ['action' => 'claim', 'code' => $code, 'account' => $firstAccount->id, 'actor' => $firstOwner->id,
        'quote' => $gifts->previewForClaim($code, $firstAccount, $firstOwner)['quote_key']];
    $secondClaim = ['action' => 'claim', 'code' => $code, 'account' => $secondAccount->id, 'actor' => $secondOwner->id,
        'quote' => $gifts->previewForClaim($code, $secondAccount, $secondOwner)['quote_key']];
    $claims = race($database, $directory, 'gift-two-recipients', [$firstClaim, $secondClaim]);
    check(count(array_filter($claims, fn ($r) => $r['outcome'] === 'success')) === 1, 'A gift was assigned twice.');
    check($firstAccount->balance()['available_minutes'] + $secondAccount->balance()['available_minutes'] === 660, 'Gift minutes duplicated or lost.');
    check(DB::table('care_hour_lots')->where('care_hour_purchase_id', $giftPurchase->id)->count() === 1, 'Gift created more than one lot.');
    $report['gift_two_recipients'] = ['assignments' => 1, 'lots' => 1, 'credited_minutes' => 660];

    [$account, $family] = seedAccount();
    [$giftPurchase, $code] = paidGift();
    $claim = ['action' => 'claim', 'code' => $code, 'account' => $account->id, 'actor' => $family->id,
        'quote' => $gifts->previewForClaim($code, $account, $family)['quote_key']];
    $direct = ['action' => 'purchase', 'account' => $account->id, 'actor' => $family->id,
        'quote' => $purchases->quote($account, 10)['quote_key'], 'request' => (string) Str::uuid()];
    $claims = race($database, $directory, 'gift-direct-welcome', [$claim, $direct]);
    check(count(array_filter($claims, fn ($r) => $r['outcome'] === 'success')) === 1, 'Gift and direct purchase both won welcome eligibility.');
    foreach ($account->purchases()->where('status', 'pending')->get() as $purchase) {
        $purchases->refresh($purchase);
    }
    if ($giftPurchase->fresh()->care_hour_account_id === null) {
        $updated = $gifts->previewForClaim($code, $account, $family);
        check($updated['bonus_minutes'] === 0, 'Gift retry re-awarded the welcome bonus.');
        $gifts->claim($code, $account, $family, $updated['quote_key']);
    } else {
        $updated = $purchases->quote($account, 10);
        check($updated['bonus_minutes'] === 0, 'Direct retry re-awarded the welcome bonus.');
        $purchases->refresh($purchases->start($account, 10, $family, $updated['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel')));
    }
    check($account->balance()['available_minutes'] === 1260 && (int) $account->lots()->sum('bonus_minutes') === 60, 'Welcome race did not conserve paid and bonus time.');
    assertConservation($account);
    $report['gift_and_direct_welcome'] = ['paid_minutes' => 1200, 'bonus_minutes' => 60, 'credited_minutes' => 1260];

    $report['gift_self_identity'] = selfClaimCheck($database, $directory);

    config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false, 'care_hours.enrollment_enabled' => false,
        'care_hours.contributions_enabled' => false, 'care_hours.gifts_enabled' => false, 'care_hours.welcome_enabled' => false]);
    $beforePaused = $account->balance();
    foreach (['homecare:reconcile-care-hour-purchases', 'care-hours:reconcile-bookings'] as $command) {
        $exit = Artisan::call($command, ['--limit' => 100]);
        file_put_contents($directory.'/paused-'.str_replace(':', '-', $command).'.log', Artisan::output());
        check($exit === 0, 'Recovery command failed while sales were paused.');
        $report['paused_recovery_commands'][$command] = ['exit_code' => $exit];
    }
    check($beforePaused === $account->balance(), 'Paused recovery changed an already issued gift balance.');
    check(! config('care_hours.enabled') && ! config('care_hours.gifts_enabled'), 'Test left exposure enabled.');

    return $report;
}

try {
    if (($argv[1] ?? '') === '--worker') {
        worker($argv[2] ?? '', $argv[3] ?? '', $argv[4] ?? '');
    }
    $settings = connectionSettings();
    $root = dirname(__DIR__, 2);
    if (in_array($argv[1] ?? '', ['--self-claim-owned', '--funding-release-owned'], true)) {
        $target = $argv[1] === '--funding-release-owned' ? 'funding-release' : 'self-claim';
        $database = $argv[2] ?? '';
        check(getenv('CARE_HOURS_CONCURRENCY_PORT') === '33308', 'Targeted reuse requires the existing isolated daemon on port 33308.');
        check((bool) preg_match('/^care_hours_concurrency_[a-z0-9_]+$/', $database), 'Invalid disposable run name.');
        $directory = $root.'/tmp/care-hours-concurrency/'.$database;
        $suffix = gmdate('Ymd_His').'-'.bin2hex(random_bytes(3));
        $targetedReportFile = $directory.'/'.$target.'-'.$suffix.'.report.json';
        $targetedFailureFile = $directory.'/'.$target.'-'.$suffix.'.failure.log';
        $owned = json_decode(file_get_contents($directory.'/report.json'), true, flags: JSON_THROW_ON_ERROR);
        check(($owned['database'] ?? '') === $database && ($owned['passed'] ?? false) && isset($owned['migration']), 'No successful owned migration rehearsal exists.');
        if ($target === 'funding-release') {
            $dataDirectory = serverConnection($settings)->query('SELECT @@datadir')->fetchColumn();
            check(strtolower(str_replace('\\', '/', rtrim($dataDirectory, '/\\'))) === strtolower(str_replace('\\', '/', $root).'/tmp/care-hours-concurrency/mysql-isolated/data'),
                'Targeted funding reuse requires the explicitly owned isolated MySQL data directory.');
        }
        $originalReportHash = hash_file('sha256', $directory.'/report.json');
        $report = ['database' => $database, 'provider_io' => false, 'original_report_preserved' => true,
            'created_at' => gmdate(DATE_ATOM), 'passed' => false];
        bootIsolated($database, $directory, $settings);
        $report['engine'] = (array) DB::selectOne('SELECT VERSION() AS version, @@version_comment AS edition, @@transaction_isolation AS isolation_level');
        $report['concurrency'] = $target === 'funding-release' ? fundingReleaseCheck($database, $directory) : selfClaimCheck($database, $directory);
        check(hash_file('sha256', $directory.'/report.json') === $originalReportHash, 'The original evidence report changed.');
        $report['passed'] = true;
        file_put_contents($targetedReportFile, json_encode($report, JSON_PRETTY_PRINT));
        echo json_encode($report + ['evidence_path' => $targetedReportFile], JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }
    if (($argv[1] ?? '') === '--resume-owned') {
        $database = $argv[2] ?? '';
        check((bool) getenv('CARE_HOURS_CONCURRENCY_PORT'), 'Resume requires the separate isolated daemon.');
        check((bool) preg_match('/^care_hours_concurrency_[a-z0-9_]+$/', $database), 'Invalid disposable run name.');
        $directory = $root.'/tmp/care-hours-concurrency/'.$database;
        $reportFile = is_file($directory.'/report.json') ? $directory.'/report.json' : $directory.'/run.json';
        $report = json_decode(file_get_contents($reportFile), true, flags: JSON_THROW_ON_ERROR);
        check(($report['database'] ?? '') === $database, 'No owned migration rehearsal exists.');
        $report['passed'] = false;
        bootIsolated($database, $directory, $settings);
        if (! isset($report['migration'])) {
            check(! \Illuminate\Support\Facades\Schema::hasTable('care_hour_accounts'), 'Cannot recreate the pre-feature migration state.');
            $report['migration'] = migrationRehearsal($directory);
            file_put_contents($directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT));
        }
        check(Artisan::call('migrate', ['--force' => true]) === 0, 'Pending additive migration failed.');
        $report['concurrency'] = concurrencyChecks($database, $directory);
        $report['passed'] = true;
        file_put_contents($directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT));
        echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }
    $database = 'care_hours_concurrency_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4));
    $directory = $root.'/tmp/care-hours-concurrency/'.$database;
    mkdir($directory.'/views', 0777, true);
    $pdo = serverConnection($settings);
    $engine = $pdo->query('SELECT VERSION() AS version, @@version_comment AS edition, @@transaction_isolation AS isolation_level')->fetch(PDO::FETCH_ASSOC);
    // No IF NOT EXISTS: never adopt or clear an existing database, even with the expected prefix.
    $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $report = ['database' => $database, 'engine' => $engine, 'provider_io' => false, 'created_at' => gmdate(DATE_ATOM)];
    file_put_contents($directory.'/run.json', json_encode($report, JSON_PRETTY_PRINT));
    bootIsolated($database, $directory, $settings);
    $report['migration'] = migrationRehearsal($directory);
    file_put_contents($directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT));
    $report['concurrency'] = concurrencyChecks($database, $directory);
    $report['passed'] = true;
    file_put_contents($directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT));
    echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $error) {
    if (isset($directory)) {
        if (isset($report)) {
            $report['passed'] = false;
            file_put_contents($targetedReportFile ?? $directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT));
        }
        file_put_contents($targetedFailureFile ?? $directory.'/failure.log', $error::class.': '.$error->getMessage().PHP_EOL.$error->getTraceAsString());
    }
    fwrite(STDERR, 'Concurrency rehearsal failed: '.$error::class.'; inspect the isolated failure.log.'.PHP_EOL);
    exit(1);
}
