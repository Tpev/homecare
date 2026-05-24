<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\SmsInbox;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSmsMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_received_texts_and_send_sms(): void
    {
        config()->set('services.twilio.bypass', true);
        config()->set('services.twilio.sms_from', '+19195550000');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        SmsMessage::query()->create([
            'direction' => SmsMessage::DIRECTION_INCOMING,
            'status' => SmsMessage::STATUS_RECEIVED,
            'from_phone' => '+19195551234',
            'to_phone' => '+19195550000',
            'body' => 'Can someone help with care tomorrow?',
            'twilio_sid' => 'SM_incoming_123',
            'received_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.sms.index'));

        $response->assertOk();
        $response->assertSee('Text Messages');
        $response->assertSee('Can someone help with care tomorrow?');
        $response->assertSee('Admin SMS');

        Livewire::actingAs($admin)
            ->test(SmsInbox::class)
            ->set('toPhone', '(919) 555-1234')
            ->set('messageBody', 'Thanks for texting. We can help.')
            ->call('sendMessage')
            ->assertHasNoErrors()
            ->assertSee('Thanks for texting. We can help.');

        $this->assertDatabaseHas('sms_messages', [
            'direction' => SmsMessage::DIRECTION_OUTGOING,
            'status' => SmsMessage::STATUS_QUEUED,
            'from_phone' => '+19195550000',
            'to_phone' => '+19195551234',
            'body' => 'Thanks for texting. We can help.',
            'sent_by_user_id' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_access_admin_sms_console(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family)
            ->get(route('admin.sms.index'))
            ->assertForbidden();
    }

    public function test_twilio_sms_webhook_validates_signature_and_stores_received_message(): void
    {
        config()->set('services.twilio.bypass', false);
        config()->set('services.twilio.auth_token', 'twilio-secret');

        $payload = [
            'AccountSid' => 'AC123',
            'Body' => 'I need a callback please.',
            'From' => '+19195551234',
            'MessageSid' => 'SM_received_123',
            'NumMedia' => '0',
            'SmsStatus' => 'received',
            'To' => '+19195550000',
        ];

        $this->post(route('webhooks.twilio.sms'), $payload, [
            'X-Twilio-Signature' => 'bad-signature',
        ])->assertUnauthorized();

        $signature = $this->twilioSignature(url('/webhooks/twilio/sms'), $payload, 'twilio-secret');

        $this->post(route('webhooks.twilio.sms'), $payload, [
            'X-Twilio-Signature' => $signature,
        ])
            ->assertOk()
            ->assertSee('<Response></Response>', false);

        $this->assertDatabaseHas('sms_messages', [
            'direction' => SmsMessage::DIRECTION_INCOMING,
            'status' => SmsMessage::STATUS_RECEIVED,
            'from_phone' => '+19195551234',
            'to_phone' => '+19195550000',
            'body' => 'I need a callback please.',
            'twilio_sid' => 'SM_received_123',
            'twilio_account_sid' => 'AC123',
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function twilioSignature(string $url, array $payload, string $token): string
    {
        ksort($payload, SORT_STRING);

        $base = $url;
        foreach ($payload as $key => $value) {
            $base .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $base, $token, true));
    }
}
