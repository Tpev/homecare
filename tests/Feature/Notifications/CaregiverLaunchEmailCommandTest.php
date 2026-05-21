<?php

namespace Tests\Feature\Notifications;

use App\Mail\CaregiverLaunchEmail;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CaregiverLaunchEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_command_sends_one_test_email_to_launch_test_recipient(): void
    {
        Mail::fake();

        User::factory()->create([
            'role' => 'caregiver',
            'email' => 'caregiver@example.com',
        ]);

        $this->artisan('lolo:send-caregiver-launch-email')
            ->assertExitCode(0);

        Mail::assertSent(CaregiverLaunchEmail::class, 1);
        Mail::assertSent(
            CaregiverLaunchEmail::class,
            fn (CaregiverLaunchEmail $mail): bool => $mail->hasTo('tpeverelli@hub.healthcare')
        );
        Mail::assertNotSent(
            CaregiverLaunchEmail::class,
            fn (CaregiverLaunchEmail $mail): bool => $mail->hasTo('caregiver@example.com')
        );
    }

    public function test_all_option_sends_to_caregivers_only_and_logs_deliveries(): void
    {
        Mail::fake();

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email' => 'caregiver@example.com',
        ]);
        $secondCaregiver = User::factory()->create([
            'role' => 'caregiver',
            'email' => 'caregiver-two@example.com',
        ]);
        User::factory()->create([
            'role' => 'family',
            'email' => 'family@example.com',
        ]);

        $this->artisan('lolo:send-caregiver-launch-email --all --force')
            ->assertExitCode(0);

        Mail::assertSent(CaregiverLaunchEmail::class, 2);
        Mail::assertSent(
            CaregiverLaunchEmail::class,
            fn (CaregiverLaunchEmail $mail): bool => $mail->hasTo($caregiver->email)
        );
        Mail::assertSent(
            CaregiverLaunchEmail::class,
            fn (CaregiverLaunchEmail $mail): bool => $mail->hasTo($secondCaregiver->email)
        );
        Mail::assertNotSent(
            CaregiverLaunchEmail::class,
            fn (CaregiverLaunchEmail $mail): bool => $mail->hasTo('family@example.com')
        );

        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $caregiver->id,
            'event_key' => 'caregiver_lolo_launch_2026_05',
            'channel' => 'email',
            'status' => 'sent',
            'dedupe_key' => 'caregiver-lolo-launch-2026-05:user-'.$caregiver->id.':email',
        ]);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $secondCaregiver->id,
            'event_key' => 'caregiver_lolo_launch_2026_05',
            'channel' => 'email',
            'status' => 'sent',
            'dedupe_key' => 'caregiver-lolo-launch-2026-05:user-'.$secondCaregiver->id.':email',
        ]);
    }

    public function test_all_option_skips_caregivers_already_logged_as_sent(): void
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email' => 'caregiver@example.com',
        ]);

        $this->artisan('lolo:send-caregiver-launch-email --all --force')
            ->assertExitCode(0);

        Mail::fake();

        $this->artisan('lolo:send-caregiver-launch-email --all --force')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('marketplace_notification_deliveries', 1);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $caregiver->id,
            'event_key' => 'caregiver_lolo_launch_2026_05',
            'channel' => 'email',
            'status' => 'sent',
        ]);
    }

    public function test_all_option_retries_failed_delivery_without_duplicate_dedupe_collision(): void
    {
        Mail::fake();

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email' => 'caregiver@example.com',
        ]);

        MarketplaceNotificationDelivery::query()->create([
            'user_id' => $caregiver->id,
            'event_key' => 'caregiver_lolo_launch_2026_05',
            'channel' => 'email',
            'status' => 'failed',
            'dedupe_key' => 'caregiver-lolo-launch-2026-05:user-'.$caregiver->id.':email',
            'payload' => ['provider_error' => 'Previous mailer issue'],
            'sent_at' => now()->subMinute(),
        ]);

        $this->artisan('lolo:send-caregiver-launch-email --all --force')
            ->assertExitCode(0);

        Mail::assertSent(CaregiverLaunchEmail::class, 1);
        $this->assertDatabaseCount('marketplace_notification_deliveries', 1);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $caregiver->id,
            'event_key' => 'caregiver_lolo_launch_2026_05',
            'channel' => 'email',
            'status' => 'sent',
            'dedupe_key' => 'caregiver-lolo-launch-2026-05:user-'.$caregiver->id.':email',
        ]);
    }
}
