<?php

namespace App\Services\CareHours;

use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourAllocation;
use App\Models\CareHourLedgerEntry;
use App\Models\CareHourLot;
use App\Models\CareHourPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CareHourLedgerService
{
    public function balance(CareHourAccount $account): array
    {
        $lots = $account->lots()->where('status', '!=', 'reversed')
            ->withSum(['allocations as reserved' => fn ($q) => $q->where('status', 'reserved')], 'minutes')
            ->withSum(['allocations as used' => fn ($q) => $q->where('status', 'used')], 'minutes')->get();
        $balance = ['available_minutes' => 0, 'reserved_minutes' => 0, 'used_minutes' => 0, 'held_minutes' => 0];
        foreach ($lots as $lot) {
            $reserved = (int) $lot->reserved;
            $used = (int) $lot->used;
            $free = $lot->total_minutes - $reserved - $used;
            if ($free < 0) {
                throw new \LogicException('Care-hour conservation failed for lot '.$lot->id.'.');
            }
            $balance['reserved_minutes'] += $reserved;
            $balance['used_minutes'] += $used;
            $balance[$lot->status === 'held' ? 'held_minutes' : 'available_minutes'] += $free;
        }

        return $balance;
    }

    public function history(CareHourAccount $account): Builder
    {
        return CareHourLedgerEntry::query()->where('care_hour_account_id', $account->id)->latest('id');
    }

    public function issuePurchase(CareHourPurchase $purchase): CareHourLot
    {
        return DB::transaction(function () use ($purchase): CareHourLot {
            $account = $this->lockAccount((int) $purchase->care_hour_account_id);
            $purchase = $purchase->newQuery()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $existing = CareHourLot::query()->where('care_hour_purchase_id', $purchase->id)->first();
            if ($existing) {
                return $existing;
            }
            if (! in_array($purchase->status, ['paid', 'issued'], true) || ! $account->active
                || strtolower((string) $purchase->currency) !== strtolower($account->currency)
                || $purchase->total_minutes < 1
                || $purchase->total_minutes !== $purchase->paid_minutes + $purchase->bonus_minutes) {
                throw ValidationException::withMessages(['care_hours' => 'This purchase cannot be credited. Please contact support.']);
            }
            $lot = CareHourLot::create([
                'care_hour_account_id' => $account->id, 'care_hour_purchase_id' => $purchase->id,
                'paid_minutes' => $purchase->paid_minutes, 'bonus_minutes' => $purchase->bonus_minutes,
                'total_minutes' => $purchase->total_minutes, 'processing_fee_cents' => $purchase->processing_fee_cents,
                'status' => 'available', 'issued_at' => now(),
            ]);
            $this->entry($account, 'purchase', $lot->total_minutes, 'Care hours added', [
                'available_delta' => $lot->total_minutes, 'care_hour_lot_id' => $lot->id,
                'care_hour_purchase_id' => $purchase->id, 'actor_user_id' => $purchase->buyer_user_id,
                'idempotency_key' => 'purchase:'.$purchase->id.':issue',
                'metadata' => ['paid_minutes' => $lot->paid_minutes, 'bonus_minutes' => $lot->bonus_minutes],
            ]);

            return $lot;
        }, 3);
    }

    public function updatePurchaseFee(CareHourPurchase $purchase, int $feeCents): void
    {
        if ($feeCents < 0) {
            throw new \InvalidArgumentException('A processing fee cannot be negative.');
        }
        DB::transaction(function () use ($purchase, $feeCents): void {
            $account = $this->lockAccount((int) $purchase->care_hour_account_id);
            $lot = CareHourLot::query()->where('care_hour_purchase_id', $purchase->id)->lockForUpdate()->first();
            if (! $lot || $lot->processing_fee_cents === $feeCents) {
                return;
            }
            $previous = $lot->processing_fee_cents;
            $lot->update(['processing_fee_cents' => $feeCents]);
            $this->entry($account, 'processing_fee', 0, 'Purchase processing fee confirmed', [
                'care_hour_lot_id' => $lot->id, 'care_hour_purchase_id' => $purchase->id,
                'metadata' => ['previous_fee_cents' => $previous, 'fee_cents' => $feeCents],
            ]);
            $this->invalidateSettlements($lot);
        }, 3);
    }

    /** Reserve up to the requested time. The caller discloses and funds any shortfall. */
    public function reserve(CareHourAccount $account, CareBooking $booking, int $targetMinutes): int
    {
        $this->assertMinutes($targetMinutes);

        return DB::transaction(function () use ($account, $booking, $targetMinutes): int {
            $account = $this->lockAccount($account->id);
            $this->assertBookingAccount($account, $booking);
            if (! $account->active) {
                throw ValidationException::withMessages(['care_hours' => 'This care recipient is unavailable.']);
            }
            if ($this->allocationsFor($booking)->where('status', 'used')->isNotEmpty()) {
                throw ValidationException::withMessages(['care_hours' => 'This visit has already used its care hours.']);
            }

            return $this->reserveLocked($account, $booking, $targetMinutes);
        }, 3);
    }

    /** Finalize approved minutes; repeat calls and downward corrections conserve the original ranges. */
    public function settle(CareBooking $booking, int $targetMinutes): array
    {
        $this->assertMinutes($targetMinutes);
        $account = $this->accountForBooking($booking);
        if (! $account) {
            return ['used_minutes' => 0, 'processing_fee_cents' => 0, 'allocations' => new Collection];
        }

        return DB::transaction(function () use ($account, $booking, $targetMinutes): array {
            $account = $this->lockAccount($account->id);
            $this->assertBookingAccount($account, $booking);
            $used = $this->activeAllocations($booking, 'used');
            $usedMinutes = (int) $used->sum('minutes');
            $toRestore = max(0, $usedMinutes - $targetMinutes);
            foreach ($used->reverse() as $allocation) {
                $restore = min($toRestore, $allocation->minutes);
                if ($restore > 0) {
                    $this->restoreAllocation($account, $allocation, $restore);
                    $toRestore -= $restore;
                }
            }
            $usedMinutes = min($usedMinutes, $targetMinutes);
            $needed = $targetMinutes - $usedMinutes;
            $this->reserveLocked($account, $booking, $needed);
            foreach ($this->activeAllocations($booking, 'reserved') as $allocation) {
                if ($allocation->lot->status !== 'available') {
                    throw ValidationException::withMessages(['care_hours' => 'Some reserved hours need payment review.']);
                }
                $allocation->update(['status' => 'used']);
                $this->entry($account, 'usage', $allocation->minutes, 'Care hours used', [
                    'care_hour_lot_id' => $allocation->care_hour_lot_id, 'care_booking_id' => $booking->id,
                    'care_hour_purchase_id' => $allocation->lot->care_hour_purchase_id,
                    'reserved_delta' => -$allocation->minutes, 'used_delta' => $allocation->minutes,
                    'metadata' => $this->rangeMetadata($allocation),
                ]);
            }
            $allocations = $this->activeAllocations($booking, 'used');
            $fees = $allocations->map(fn (CareHourAllocation $a) => $a->feeCents());

            return [
                'used_minutes' => (int) $allocations->sum('minutes'),
                'processing_fee_cents' => $fees->containsStrict(null) ? null : (int) $fees->sum(),
                'allocations' => $allocations,
            ];
        }, 3);
    }

    public function release(CareBooking $booking): void
    {
        $account = $this->accountForBooking($booking);
        if (! $account) {
            return;
        }
        DB::transaction(function () use ($account, $booking): void {
            $account = $this->lockAccount($account->id);
            foreach ($this->activeAllocations($booking, 'reserved') as $allocation) {
                $this->restoreAllocation($account, $allocation, $allocation->minutes);
            }
        }, 3);
    }

    public function allocationsFor(CareBooking $booking): Collection
    {
        return CareHourAllocation::query()->with('lot.purchase')->where('care_booking_id', $booking->id)
            ->whereIn('status', ['reserved', 'used'])->orderBy('care_hour_lot_id')->orderBy('start_minute')->get();
    }

    public function holdPurchase(CareHourPurchase $purchase, string $reason, ?User $actor = null): void
    {
        $this->changeLotState($purchase, 'held', $reason, $actor);
    }

    public function releasePurchaseHold(CareHourPurchase $purchase, string $reason, ?User $actor = null): void
    {
        $this->changeLotState($purchase, 'available', $reason, $actor);
    }

    public function reversePurchase(CareHourPurchase $purchase, string $reason, ?User $actor = null): void
    {
        $this->changeLotState($purchase, 'reversed', $reason, $actor);
    }

    private function changeLotState(CareHourPurchase $purchase, string $status, string $reason, ?User $actor): void
    {
        // An unclaimed gift has a charge, but no recipient lot to hold or release yet.
        if (! $purchase->care_hour_account_id && $purchase->gift()->exists()) {
            return;
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required.');
        }
        DB::transaction(function () use ($purchase, $status, $reason, $actor): void {
            $account = $this->lockAccount((int) $purchase->care_hour_account_id);
            $lot = CareHourLot::query()->where('care_hour_purchase_id', $purchase->id)->lockForUpdate()->first();
            if (! $lot || $lot->status === $status) {
                return;
            }
            if ($lot->status === 'reversed') {
                throw ValidationException::withMessages(['care_hours' => 'A reversed purchase cannot be reissued.']);
            }
            $allocated = (int) $lot->allocations()->whereIn('status', ['reserved', 'used'])->sum('minutes');
            if ($status === 'reversed' && $allocated > 0) {
                throw ValidationException::withMessages(['care_hours' => 'Resolve reserved or used hours before reversing this purchase.']);
            }
            $free = $lot->total_minutes - $allocated;
            $oldStatus = $lot->status;
            $lot->update(['status' => $status, 'hold_reason' => $status === 'held' ? Str::limit($reason, 250, '') : null]);
            $deltas = ['available_delta' => 0, 'held_delta' => 0];
            $deltas[$oldStatus === 'held' ? 'held_delta' : 'available_delta'] -= $free;
            if ($status !== 'reversed') {
                $deltas[$status === 'held' ? 'held_delta' : 'available_delta'] += $free;
            }
            $this->entry($account, $status === 'reversed' ? 'reversal' : ($status === 'held' ? 'hold' : 'hold_released'), $free,
                $status === 'held' ? 'Hours on hold for payment review' : ($status === 'reversed' ? 'Purchase corrected' : 'Hours available again'),
                $deltas + [
                    'care_hour_lot_id' => $lot->id, 'care_hour_purchase_id' => $purchase->id,
                    'actor_user_id' => $actor?->id, 'metadata' => ['reason' => $reason, 'previous_status' => $oldStatus],
                ]);
            $this->invalidateSettlements($lot);
        }, 3);
    }

    private function invalidateSettlements(CareHourLot $lot): void
    {
        if (! Schema::hasTable('care_hour_settlements')) {
            return;
        }
        // This durable marker is committed with the lot change. Recovery includes previously paid visits.
        DB::table('care_hour_settlements')->whereIn('care_booking_id', $lot->allocations()
            ->select('care_booking_id')->where('status', 'used'))->update([
                'status' => 'reconciliation_pending', 'revision' => DB::raw('revision + 1'), 'updated_at' => now(),
            ]);
    }

    private function reserveLocked(CareHourAccount $account, CareBooking $booking, int $target): int
    {
        $reserved = $this->activeAllocations($booking, 'reserved');
        $current = (int) $reserved->sum('minutes');
        $excess = max(0, $current - $target);
        foreach ($reserved->reverse() as $allocation) {
            $release = min($excess, $allocation->minutes);
            if ($release > 0) {
                $this->restoreAllocation($account, $allocation, $release);
                $excess -= $release;
                $current -= $release;
            }
        }
        $needed = $target - $current;
        if ($needed <= 0) {
            return $current;
        }
        $lots = $account->lots()->where('status', 'available')->orderBy('issued_at')->orderBy('id')->lockForUpdate()->get();
        foreach ($lots as $lot) {
            foreach ($this->freeRanges($lot) as [$start, $length]) {
                $take = min($needed, $length);
                if ($take <= 0) {
                    break 2;
                }
                $allocation = CareHourAllocation::create([
                    'care_hour_account_id' => $account->id, 'care_hour_lot_id' => $lot->id,
                    'care_booking_id' => $booking->id, 'start_minute' => $start, 'minutes' => $take, 'status' => 'reserved',
                ]);
                $this->entry($account, 'reservation', $take, 'Hours reserved for a visit', [
                    'care_hour_lot_id' => $lot->id, 'care_booking_id' => $booking->id,
                    'care_hour_purchase_id' => $lot->care_hour_purchase_id,
                    'available_delta' => -$take, 'reserved_delta' => $take,
                    'metadata' => $this->rangeMetadata($allocation),
                ]);
                $needed -= $take;
                $current += $take;
            }
        }

        return $current;
    }

    private function restoreAllocation(CareHourAccount $account, CareHourAllocation $allocation, int $minutes): void
    {
        $originalStatus = $allocation->status;
        $start = $allocation->start_minute + $allocation->minutes - $minutes;
        if ($minutes === $allocation->minutes) {
            $allocation->update(['status' => 'released']);
        } else {
            $allocation->update(['minutes' => $allocation->minutes - $minutes]);
            CareHourAllocation::create([
                'care_hour_account_id' => $account->id, 'care_hour_lot_id' => $allocation->care_hour_lot_id,
                'care_booking_id' => $allocation->care_booking_id, 'start_minute' => $start,
                'minutes' => $minutes, 'status' => 'released',
            ]);
        }
        $held = $allocation->lot->status === 'held';
        $this->entry($account, $originalStatus === 'used' ? 'usage_correction' : 'release', $minutes, 'Care hours returned', [
            'care_hour_lot_id' => $allocation->care_hour_lot_id, 'care_booking_id' => $allocation->care_booking_id,
            'care_hour_purchase_id' => $allocation->lot->care_hour_purchase_id,
            $originalStatus === 'used' ? 'used_delta' : 'reserved_delta' => -$minutes,
            $held ? 'held_delta' : 'available_delta' => $minutes,
            'metadata' => ['start_minute' => $start, 'minutes' => $minutes, 'original_status' => $originalStatus],
        ]);
    }

    private function freeRanges(CareHourLot $lot): array
    {
        $allocations = $lot->allocations()->whereIn('status', ['reserved', 'used'])->orderBy('start_minute')->lockForUpdate()->get();
        $cursor = 0;
        $ranges = [];
        foreach ($allocations as $allocation) {
            if ($allocation->start_minute < $cursor) {
                throw new \LogicException('Care-hour allocations overlap.');
            }
            if ($allocation->start_minute > $cursor) {
                $ranges[] = [$cursor, $allocation->start_minute - $cursor];
            }
            $cursor = $allocation->start_minute + $allocation->minutes;
        }
        if ($cursor < $lot->total_minutes) {
            $ranges[] = [$cursor, $lot->total_minutes - $cursor];
        }
        if ($cursor > $lot->total_minutes) {
            throw new \LogicException('Care-hour allocation exceeds its purchase.');
        }

        return $ranges;
    }

    private function activeAllocations(CareBooking $booking, string $status): Collection
    {
        return CareHourAllocation::query()->with('lot.purchase')->where('care_booking_id', $booking->id)
            ->where('status', $status)->orderBy('care_hour_lot_id')->orderBy('start_minute')->lockForUpdate()->get();
    }

    private function lockAccount(int $id): CareHourAccount
    {
        return CareHourAccount::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function accountForBooking(CareBooking $booking): ?CareHourAccount
    {
        $accountId = CareHourAllocation::query()->where('care_booking_id', $booking->id)->value('care_hour_account_id');
        if (! $accountId && Schema::hasTable('care_hour_booking_fundings')) {
            $accountId = DB::table('care_hour_booking_fundings')->where('care_booking_id', $booking->id)->value('care_hour_account_id');
        }

        return $accountId ? CareHourAccount::find($accountId) : app(CareHourIdentityService::class)->forBooking($booking);
    }

    private function assertBookingAccount(CareHourAccount $account, CareBooking $booking): void
    {
        $sameFamily = $booking->family_account_id
            ? (int) $booking->family_account_id === (int) $account->family_account_id
            : (int) $booking->family_user_id === (int) $account->familyAccount->owner_user_id;
        $other = CareHourAllocation::query()->where('care_booking_id', $booking->id)
            ->where('care_hour_account_id', '!=', $account->id)->whereIn('status', ['reserved', 'used'])->exists();
        if (! $sameFamily || $other) {
            throw ValidationException::withMessages(['care_hours' => 'These care hours cannot fund this visit.']);
        }
    }

    private function assertMinutes(int $minutes): void
    {
        if ($minutes < 0) {
            throw new \InvalidArgumentException('Care minutes cannot be negative.');
        }
    }

    private function rangeMetadata(CareHourAllocation $allocation): array
    {
        return ['start_minute' => $allocation->start_minute, 'minutes' => $allocation->minutes];
    }

    private function entry(CareHourAccount $account, string $type, int $minutes, string $description, array $attributes = []): void
    {
        CareHourLedgerEntry::create($attributes + [
            'care_hour_account_id' => $account->id, 'type' => $type, 'minutes' => $minutes,
            'description' => $description, 'idempotency_key' => (string) Str::uuid(),
        ]);
    }
}
