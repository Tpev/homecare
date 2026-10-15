<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Caregiver\NotificationsCenter;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Services\Marketplace\CareRequestRecruitmentEmail;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CareRequestRecruitmentEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config([
            'app.timezone' => 'America/New_York',
            'marketplace.pricing_v2.caregiver_gross_hourly_cents' => 2700,
        ]);
    }

    public function test_recruitment_email_uses_lolo_brand_and_tracked_link_to_the_exact_job(): void
    {
        [$caregiver, $request] = $this->context();
        $mail = $this->mail($caregiver, $request);
        $html = view($mail->view['html'], $mail->viewData)->render();
        $text = html_entity_decode(view($mail->view['text'], $mail->viewData)->render());
        $directUrl = route('care-requests.apply', $request->id);

        $this->assertSame('emails.notifications.caregiver-recruitment-html', $mail->view['html']);
        $this->assertSame('emails.notifications.caregiver-recruitment-text', $mail->view['text']);
        $this->assertSame('LoLo Care', $mail->from[1]);
        $this->assertStringStartsWith('[LoLo Care] ', $mail->subject);
        $this->assertSame('View job & apply', $mail->viewData['ctaLabel']);
        $this->assertSame($directUrl, $mail->viewData['rawUrl']);
        $this->assertSame(route('caregiver.notifications.index'), $mail->viewData['preferencesUrl']);
        $this->assertStringContainsString('href="'.$mail->viewData['url'].'"', $html);
        $this->assertStringContainsString($mail->viewData['url'], $text);
        $this->assertStringContainsString($directUrl, $html);
        $this->assertStringContainsString('Direct job link: '.$directUrl, $text);
        $this->assertStringContainsString('Hi Sarah,', $html);
        $this->assertStringContainsString('lolo-lockup-warm-1024.png', $html);
        $this->assertStringContainsString('This care request is still looking for the right person', $html);
        $this->assertStringContainsString('build trust, and open the door to future opportunities', $text);

        $this->get($mail->viewData['url'])->assertRedirect($directUrl);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $caregiver->id,
            'event_key' => MarketplaceEvent::CAREGIVER_RECRUITMENT,
            'channel' => 'email',
            'click_count' => 1,
        ]);
        $this->assertDatabaseCount('care_request_applications', 0);
        $this->assertDatabaseCount('care_request_invitations', 0);
    }

    public function test_email_shares_public_job_details_and_gross_caregiver_rate_without_private_information(): void
    {
        [$caregiver, $request] = $this->context();
        $request->recipient()->create([
            'full_name' => 'Private Recipient Name',
            'relationship_to_family' => 'parent',
            'care_notes' => 'Sensitive recipient notes',
        ]);
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $request->tasks()->attach($task, ['task_note' => 'Sensitive task instructions']);

        $mail = $this->mail($caregiver, $request);
        $delivery = MarketplaceNotificationDelivery::query()
            ->where('event_key', MarketplaceEvent::CAREGIVER_RECRUITMENT)->sole();

        foreach (['html', 'text'] as $format) {
            $rendered = html_entity_decode(view($mail->view[$format], $mail->viewData)->render());
            foreach (['Morning companionship', 'Raleigh, NC', 'Companionship', '3 hours', '$27.00/hour before Stripe processing fees'] as $expected) {
                $this->assertStringContainsString($expected, $rendered);
            }
            foreach (['480 Secret Street', 'Apartment 42', 'Door code 1234', 'Private Recipient Name', 'Sensitive recipient notes', 'Sensitive task instructions', 'Private additional information', 'Family budget', '$95.00'] as $private) {
                $this->assertStringNotContainsString($private, $rendered);
                $this->assertStringNotContainsString($private, json_encode($delivery->payload));
            }
        }

        $this->assertSame(
            app(CareRequestRecruitmentEmail::class)->content($caregiver, $request)['payload']['email_details'],
            $delivery->payload['email_details'],
            'Generic notification enrichment must preserve recruitment-only job details.',
        );
    }

    public function test_recurring_email_uses_recurring_schedule_and_relationship_copy(): void
    {
        [$caregiver, $request] = $this->context();
        $request->updateQuietly([
            'request_type' => CareRequest::TYPE_RECURRING,
            'requested_start_at' => null,
            'requested_end_at' => null,
            'recurring_days' => [1, 3],
            'recurring_start_time' => '09:00',
            'recurring_end_time' => '12:00',
            'recurring_starts_on' => '2026-10-19',
            'recurring_ends_on' => '2026-11-30',
        ]);
        $mail = $this->mail($caregiver, $request);

        foreach (['html', 'text'] as $format) {
            $rendered = html_entity_decode(view($mail->view[$format], $mail->viewData)->render());
            $this->assertStringContainsString('build a regular care relationship', $rendered);
            $this->assertStringContainsString('Recurring care', $rendered);
            $this->assertStringContainsString($request->recurringScheduleLabel(), $rendered);
            $this->assertStringContainsString('From October 19, 2026 through November 30, 2026', $rendered);
            $this->assertStringNotContainsString('A smaller job', $rendered);
        }
    }

    public function test_overnight_email_includes_the_end_date_to_keep_the_schedule_clear(): void
    {
        [$caregiver, $request] = $this->context();
        $request->updateQuietly([
            'requested_start_at' => '2026-10-20 22:00:00',
            'requested_end_at' => '2026-10-21 07:00:00',
        ]);
        $content = app(CareRequestRecruitmentEmail::class)->content($caregiver, $request);
        $when = collect($content['payload']['email_details'])->firstWhere('label', 'When')['value'];

        $this->assertStringContainsString('October 20, 2026', $when);
        $this->assertStringContainsString('October 21, 2026', $when);
    }

    public function test_caregiver_can_disable_lolo_recruitment_emails_in_notification_preferences(): void
    {
        [$caregiver, $request] = $this->context();

        Livewire::actingAs($caregiver)
            ->test(NotificationsCenter::class)
            ->assertSee('Care opportunities from LoLo')
            ->set('preferences.'.MarketplaceEvent::CAREGIVER_RECRUITMENT.'.email', false)
            ->call('savePreferences')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $caregiver->id,
            'event_key' => MarketplaceEvent::CAREGIVER_RECRUITMENT,
            'email_enabled' => false,
        ]);
        $this->send($caregiver, $request);

        $this->assertDatabaseMissing('marketplace_notification_deliveries', [
            'user_id' => $caregiver->id,
            'event_key' => MarketplaceEvent::CAREGIVER_RECRUITMENT,
            'channel' => 'email',
        ]);
    }

    private function mail(User $caregiver, CareRequest $request): MailMessage
    {
        $this->send($caregiver, $request);
        $notification = Notification::sent($caregiver, MarketplaceEventNotification::class)
            ->first(fn (MarketplaceEventNotification $notification) => $notification->toArray($caregiver)['event_key'] === MarketplaceEvent::CAREGIVER_RECRUITMENT);

        $this->assertNotNull($notification);

        return $notification->toMail($caregiver);
    }

    private function send(User $caregiver, CareRequest $request): void
    {
        $content = app(CareRequestRecruitmentEmail::class)->content($caregiver, $request);
        app(MarketplaceNotificationService::class)->notify(
            recipients: $caregiver,
            eventKey: MarketplaceEvent::CAREGIVER_RECRUITMENT,
            title: $content['title'],
            body: $content['body'],
            url: route('care-requests.apply', $request->id),
            payload: $content['payload'],
            subject: $request,
            dedupeKey: 'recruitment-email-'.$request->id.'-'.$caregiver->id,
            channelOverrides: ['in_app' => false],
        );
    }

    /** @return array{User,CareRequest} */
    private function context(): array
    {
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Sarah Smith']);
        $family = User::factory()->create(['role' => 'family']);
        $request = CareRequest::query()->createQuietly([
            'family_user_id' => $family->id,
            'title' => 'Morning companionship',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => '2026-10-20 09:00:00',
            'requested_end_at' => '2026-10-20 12:00:00',
            'city' => 'Raleigh',
            'state' => 'NC',
            'address_line1' => '480 Secret Street',
            'address_line2' => 'Apartment 42',
            'zip' => '27601',
            'home_access_notes' => 'Door code 1234',
            'additional_info' => 'Private additional information',
            'budget_min' => 95,
            'budget_max' => 95,
        ]);

        return [$caregiver, $request];
    }
}
