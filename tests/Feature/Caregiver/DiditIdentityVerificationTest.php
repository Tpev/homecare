<?php

namespace Tests\Feature\Caregiver;

use App\Models\CaregiverIdentityVerification;
use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiditIdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_open_identity_verification_page(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'draft']);

        $this->actingAs($caregiver)
            ->get(route('caregiver.verification.show'))
            ->assertOk()
            ->assertSee('Identity Verification');
    }

    public function test_caregiver_can_start_didit_session_and_is_redirected(): void
    {
        config()->set('services.didit.api_key', 'test-key');
        config()->set('services.didit.workflow_id', 'workflow-123');
        config()->set('services.didit.base_url', 'https://verification.didit.me');

        Http::fake([
            'https://verification.didit.me/v3/session/*' => Http::response([
                'session_id' => 'sess_123',
                'session_token' => 'token_123',
                'verification_url' => 'https://verify.didit.me/session/sess_123',
                'status' => 'Not Started',
            ], 201),
        ]);

        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'draft']);

        $response = $this->actingAs($caregiver)->post(route('caregiver.verification.session'));

        $response->assertRedirect('https://verify.didit.me/session/sess_123');

        $this->assertDatabaseHas('caregiver_identity_verifications', [
            'user_id' => $caregiver->id,
            'didit_session_id' => 'sess_123',
            'status' => CaregiverIdentityVerification::STATUS_NOT_STARTED,
        ]);
        $this->assertDatabaseHas('caregiver_profiles', [
            'user_id' => $caregiver->id,
            'identity_verification_status' => CaregiverIdentityVerification::STATUS_NOT_STARTED,
            'identity_verification_session_id' => 'sess_123',
        ]);
    }

    public function test_caregiver_can_restart_didit_after_closing_first_session(): void
    {
        config()->set('services.didit.api_key', 'test-key');
        config()->set('services.didit.workflow_id', 'workflow-123');
        config()->set('services.didit.base_url', 'https://verification.didit.me');

        Http::fake([
            'https://verification.didit.me/v3/session/*' => Http::sequence()
                ->push([
                    'session_id' => 'sess_first',
                    'verification_url' => 'https://verify.didit.me/session/sess_first',
                    'status' => 'Not Started',
                ], 201)
                ->push([
                    'session_id' => 'sess_second',
                    'verification_url' => 'https://verify.didit.me/session/sess_second',
                    'status' => 'Not Started',
                ], 201),
        ]);

        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'draft']);

        $this->actingAs($caregiver)
            ->post(route('caregiver.verification.session'))
            ->assertRedirect('https://verify.didit.me/session/sess_first');

        $this->actingAs($caregiver)
            ->post(route('caregiver.verification.session'))
            ->assertRedirect('https://verify.didit.me/session/sess_second');

        $this->assertDatabaseCount('caregiver_identity_verifications', 2);
        $this->assertDatabaseHas('caregiver_profiles', [
            'user_id' => $caregiver->id,
            'identity_verification_session_id' => 'sess_second',
        ]);

        $vendorData = Http::recorded()
            ->map(fn (array $record) => (string) ($record[0]['vendor_data'] ?? ''))
            ->filter()
            ->values();

        $this->assertCount(2, $vendorData);
        $this->assertCount(2, $vendorData->unique());
        $this->assertTrue($vendorData->every(fn (string $value): bool => str_contains($value, '_attempt_')));
    }

    public function test_didit_webhook_approved_updates_profile_and_logs_moderation_event(): void
    {
        config()->set('services.didit.webhook_secret', 'webhook-secret');

        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'under_review',
            'identity_verification_status' => 'in_progress',
        ]);

        CaregiverIdentityVerification::query()->create([
            'caregiver_profile_id' => $profile->id,
            'user_id' => $caregiver->id,
            'didit_session_id' => 'sess_approved_1',
            'status' => CaregiverIdentityVerification::STATUS_IN_PROGRESS,
            'verification_url' => 'https://verify.didit.me/session/sess_approved_1',
            'started_at' => now(),
        ]);

        $payload = [
            'session_id' => 'sess_approved_1',
            'status' => 'Approved',
            'vendor_data' => 'caregiver_user_'.$caregiver->id.'_profile_'.$profile->id,
            'decision' => [
                'id_verifications' => ['status' => 'Approved'],
                'liveness_checks' => ['status' => 'Approved', 'score' => 0.99],
                'face_matches' => ['status' => 'Approved', 'score' => 97],
            ],
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $rawBody, 'webhook-secret');

        $this->postJson(route('webhooks.didit.identity'), $payload, [
            'X-Signature-V2' => $signature,
        ])->assertOk();

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'identity_verification_status' => CaregiverIdentityVerification::STATUS_APPROVED,
            'identity_verification_session_id' => 'sess_approved_1',
        ]);

        $fresh = $profile->fresh();
        $this->assertNotNull($fresh?->identity_verified_at);

        $this->assertDatabaseHas('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'identity_auto_verified',
        ]);
    }

    public function test_didit_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.didit.webhook_secret', 'webhook-secret');

        $payload = [
            'session_id' => 'sess_invalid',
            'status' => 'Approved',
        ];

        $this->postJson(route('webhooks.didit.identity'), $payload, [
            'X-Signature-V2' => 'bad-signature',
        ])->assertUnauthorized();
    }
}
