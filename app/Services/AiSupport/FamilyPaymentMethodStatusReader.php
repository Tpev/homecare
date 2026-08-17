<?php

namespace App\Services\AiSupport;

use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Payments\FamilyBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

class FamilyPaymentMethodStatusReader
{
    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly FamilyBillingService $billing,
    ) {}

    /**
     * @return array{
     *   can_manage:bool,
     *   attention:string,
     *   ready:bool,
     *   card:array{brand:string,last4:string,exp_month:int,exp_year:int}|null,
     *   checked_at:string,
     *   state_hash:string
     * }
     */
    public function read(User $actor): array
    {
        if ($actor->role !== 'family' || ! $this->familyAccounts->membershipFor($actor, false)) {
            throw new AuthorizationException('An active Family Account is required.');
        }

        $summary = $this->billing->summaryFor($actor);
        $card = $this->safeCard((array) ($summary['card'] ?? []));
        $ready = (bool) ($summary['ready'] ?? false) && $card !== null;

        return $this->normalized(
            true,
            $ready ? $this->attentionFor($card) : 'missing',
            $ready,
            $card,
        );
    }

    /** @param array<string,mixed> $card @return array{brand:string,last4:string,exp_month:int,exp_year:int}|null */
    private function safeCard(array $card): ?array
    {
        $brand = strtolower(trim((string) ($card['brand'] ?? '')));
        $last4 = trim((string) ($card['last4'] ?? ''));
        $month = (int) ($card['exp_month'] ?? 0);
        $year = (int) ($card['exp_year'] ?? 0);

        if ($brand === '' || preg_match('/\A\d{4}\z/', $last4) !== 1
            || $month < 1 || $month > 12 || $year < 2000 || $year > 2200) {
            return null;
        }

        return [
            'brand' => preg_replace('/[^a-z0-9_-]/', '', $brand) ?: 'card',
            'last4' => $last4,
            'exp_month' => $month,
            'exp_year' => $year,
        ];
    }

    /** @param array{brand:string,last4:string,exp_month:int,exp_year:int} $card */
    private function attentionFor(array $card): string
    {
        $expiresAt = CarbonImmutable::create(
            $card['exp_year'],
            $card['exp_month'],
            1,
            0,
            0,
            0,
            config('app.timezone'),
        )->endOfMonth();

        if ($expiresAt->isPast()) {
            return 'expired';
        }

        return $expiresAt->lessThanOrEqualTo(now()->addDays(60)) ? 'expiring_soon' : 'ready';
    }

    /**
     * @param  array{brand:string,last4:string,exp_month:int,exp_year:int}|null  $card
     * @return array{can_manage:bool,attention:string,ready:bool,card:array{brand:string,last4:string,exp_month:int,exp_year:int}|null,checked_at:string,state_hash:string}
     */
    private function normalized(bool $canManage, string $attention, bool $ready, ?array $card): array
    {
        $facts = [
            'can_manage' => $canManage,
            'attention' => $attention,
            'ready' => $ready,
            'card' => $card,
        ];

        return [
            ...$facts,
            'checked_at' => now()->toIso8601String(),
            'state_hash' => hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR)),
        ];
    }
}
