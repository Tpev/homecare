<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\CareHourGiftClaimIntent;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public static function registrationJourneys(): array
    {
        return ['ordinary signup' => [false], 'gift claim signup' => [true]];
    }

    #[DataProvider('registrationJourneys')]
    public function test_signup_sends_one_operations_alert_per_recipient_and_one_family_welcome(bool $claimingGift): void
    {
        config([
            'mail.default' => 'array',
            'marketplace.ops_alert_recipients' => ['ops@example.test', 'backup@example.test'],
            'marketplace.ops_alert_recipient_replacements' => [],
        ]);
        $messages = [];
        Event::listen(MessageSent::class, function (MessageSent $event) use (&$messages): void {
            $messages[] = [
                'subject' => $event->message->getSubject(),
                'recipients' => array_map(fn ($address) => $address->getAddress(), $event->message->getTo()),
            ];
        });
        if ($claimingGift) {
            CareHourGiftClaimIntent::put('GIFT-TEST-PRIVATE-CLAIM', [
                'paid_minutes' => 600, 'conditional_bonus_minutes' => 60,
            ]);
        }

        Volt::test('pages.auth.register')
            ->set('name', 'Linda Bennett')->set('email', 'linda@example.test')
            ->set('password', 'password')->set('password_confirmation', 'password')
            ->set('accept_terms', true)->call('register')->assertHasNoErrors()
            ->assertRedirect(route($claimingGift ? 'family.care-hours.claim' : 'family.onboarding', absolute: false));

        $this->assertCount(2, $messages, 'Signup should send one operations alert and one family welcome.');
        $operations = collect($messages)->where('subject', '[LoLo Care] New family registration: linda@example.test');
        $this->assertCount(1, $operations);
        $this->assertEqualsCanonicalizing(['ops@example.test', 'backup@example.test'], $operations->sole()['recipients']);
        foreach (['ops@example.test', 'backup@example.test'] as $recipient) {
            $this->assertSame(1, $operations->sum(fn ($message) => count(array_filter(
                $message['recipients'], fn ($address) => $address === $recipient,
            ))));
        }
        $welcome = collect($messages)->where('subject', '[LoLo Care] Welcome to LoLo Care');
        $this->assertCount(1, $welcome);
        $this->assertSame(['linda@example.test'], $welcome->sole()['recipients']);
        $user = User::where('email', 'linda@example.test')->sole();
        $this->assertSame(1, $user->notifications()->where('data->event_key', MarketplaceEvent::FAMILY_WELCOME)->count());
    }
}
