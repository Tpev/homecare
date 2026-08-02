<?php

namespace Tests\Feature\Notifications;

use App\Mail\Ops\CallbackRequestOpsAlertMail;
use App\Mail\Ops\CaregiverReadyForReviewOpsAlertMail;
use App\Mail\Ops\FamilyRegisteredOpsAlertMail;
use App\Mail\Ops\NewCareRequestOpsAlertMail;
use App\Mail\Ops\SupportTicketCreatedOpsAlertMail;
use App\Mail\Ops\UserRegisteredOpsAlertMail;
use App\Mail\Ops\VoiceCallReportOpsAlertMail;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Observers\SupportTicketObserver;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OpsMailBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_operations_email_renders_with_lolo_care_branding_and_plain_text(): void
    {
        config()->set('marketplace.ops_alert_recipients', []);

        $family = User::factory()->create([
            'role' => 'family',
            'name' => 'Barbara Family',
            'phone' => '919-555-0100',
        ]);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Charles Caregiver',
        ]);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'pending_review',
        ]);
        $lead = Lead::query()->create([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Johnny Caller',
            'email' => 'johnny@example.com',
            'phone' => '919-555-0111',
            'status' => 'new',
            'data' => ['callback_time_label' => 'Tomorrow morning', 'service_type' => 'Companionship'],
        ]);
        $careRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Weekday companionship',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'address_line1' => '100 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $ticket = SupportTicket::query()->create([
            'opener_user_id' => $family->id,
            'category' => 'billing',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'high',
            'subject' => 'Question about a visit charge',
            'description' => 'Please review the billed visit time.',
        ])->load('opener');

        $mails = [
            new CallbackRequestOpsAlertMail($lead),
            new CaregiverReadyForReviewOpsAlertMail($caregiver, $profile),
            new FamilyRegisteredOpsAlertMail($family),
            new NewCareRequestOpsAlertMail($careRequest),
            new SupportTicketCreatedOpsAlertMail($ticket),
            new UserRegisteredOpsAlertMail($caregiver),
            new VoiceCallReportOpsAlertMail([
                'call_sid' => 'CA123',
                'name' => 'Johnny Caller',
                'phone' => '919-555-0111',
                'outcome' => 'callback_requested',
                'summary' => 'Caller would like help arranging companionship.',
                'transcript' => 'Caller: I need help. Agent: We can arrange a callback.',
            ]),
        ];

        foreach ($mails as $mail) {
            $this->assertBrandedAndRenderable($mail);
        }
    }

    public function test_new_support_request_alert_is_sent_to_the_configured_operations_list(): void
    {
        Mail::fake();
        Notification::fake();
        config()->set('marketplace.ops_alert_recipients', ['ops@example.com', 'backup@example.com']);
        $family = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = SupportTicket::query()->create([
            'opener_user_id' => $family->id,
            'category' => 'visit',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'urgent',
            'subject' => 'Care visit needs review',
            'description' => 'Please review this visit as soon as possible.',
        ]);

        app(SupportTicketObserver::class)->created($ticket->load('opener'));

        Mail::assertSent(SupportTicketCreatedOpsAlertMail::class, function (SupportTicketCreatedOpsAlertMail $mail) use ($ticket): bool {
            return $mail->hasTo('ops@example.com')
                && $mail->hasTo('backup@example.com')
                && $mail->ticket->is($ticket);
        });
        Notification::assertSentTo($admin, MarketplaceEventNotification::class, function (MarketplaceEventNotification $notification, array $channels) use ($admin): bool {
            return $channels === ['database']
                && data_get($notification->toArray($admin), 'event_key') === MarketplaceEvent::SUPPORT_TICKET_CREATED;
        });
        $this->assertDatabaseMissing('marketplace_notification_deliveries', [
            'user_id' => $admin->id,
            'event_key' => MarketplaceEvent::SUPPORT_TICKET_CREATED,
            'channel' => 'email',
        ]);
    }

    private function assertBrandedAndRenderable(Mailable $mail): void
    {
        $envelope = $mail->envelope();
        $content = $mail->content();
        $html = $mail->render();
        $text = view($content->text, $content->with)->render();
        $from = is_array($envelope->from) ? ($envelope->from[0] ?? null) : $envelope->from;

        $this->assertStringStartsWith('[LoLo Care]', (string) $envelope->subject);
        $this->assertSame('LoLo Care', $from?->name);
        $this->assertStringContainsString('LoLo Care', $html);
        $this->assertStringContainsString('lolo-lockup-warm-1024.png', $html);
        $this->assertStringContainsString('LoLo Care Operations', $text);
        $this->assertStringNotContainsString('[HomeCare]', $html);
        $this->assertStringNotContainsString('HomeCare Hub', $html);
    }
}
