<?php

namespace App\Services\Ops;

use App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ManagedCareOpsContext
{
    /** Resolve through fresh ownership relations, including selected-day arrangements. */
    public function resolve(Model $subject): ?array
    {
        $found = [];
        $this->walk($subject, $found, []);
        $request = $found['request'] ?? null;
        $booking = $found['booking'] ?? null;
        $plan = $found['plan'] ?? null;
        if (! $request) {
            return null;
        }
        if (! ($subject instanceof Models\CarePlan || $subject instanceof Models\CarePlanScheduleChange || $subject instanceof Models\CompletedExtraVisitRequest)) {
            $booking ??= $request->booking()->first();
        }
        $plan ??= $booking?->carePlan;
        $source = $plan?->sourceCareRequest()->first() ?? $request->carePlan?->sourceCareRequest;
        $root = $source ?? $request;
        if ($arrangementId = $plan?->care_arrangement_id ?: $root->care_arrangement_id ?: $request->care_arrangement_id) {
            $root = Models\CareArrangement::find($arrangementId)?->sourceRequest ?? $root;
        }
        if (! $request->staff_managed && ! $source?->staff_managed && ! $root->staff_managed) {
            return null;
        }
        $caregiverId = $subject->getAttribute('caregiver_user_id') ?: ($found['conversation']->caregiver_user_id ?? null)
            ?: $booking?->caregiver_user_id ?: $plan?->caregiver_user_id;
        $caregiver = $caregiverId ? Models\User::find($caregiverId) : null;
        $zone = $plan?->timezone ?: config('app.timezone', 'America/New_York');

        return ['request' => $request, 'root' => $root, 'booking' => $booking, 'plan' => $plan,
            'conversation' => $found['conversation'] ?? null, 'caregiver' => $caregiver, 'timezone' => $zone];
    }

    private function walk(Model $model, array &$found, array $visited, int $depth = 0): void
    {
        $key = $model::class.':'.$model->getKey();
        if ($depth > 7 || isset($visited[$key])) {
            return;
        }
        $visited[$key] = true;
        foreach (['request' => Models\CareRequest::class, 'booking' => Models\CareBooking::class,
            'plan' => Models\CarePlan::class, 'conversation' => Models\CareRequestConversation::class] as $name => $class) {
            if ($model instanceof $class) {
                $found[$name] ??= $model;
            }
        }
        foreach (['booking', 'careRequest', 'conversation', 'carePlan', 'plan', 'sourceCareRequest', 'funding', 'settlement', 'ticket'] as $relationName) {
            if (method_exists($model, $relationName)) {
                $relation = $model->{$relationName}();
                if ($relation instanceof BelongsTo && ($related = $relation->first())) {
                    $this->walk($related, $found, $visited, $depth + 1);
                }
            }
        }
    }

    public function actor(Model $subject, bool $created): ?Models\User
    {
        if ($actor = auth()->user()) {
            return $actor;
        }
        $fields = $created
            ? ['sender_user_id', 'actor_user_id', 'reporter_user_id', 'requester_user_id', 'requested_by_user_id', 'invited_by_user_id', 'created_by_user_id', 'opener_user_id', 'initiated_by_user_id']
            : ['resolved_by_user_id', 'responded_by_user_id', 'approved_by_user_id', 'family_confirmed_by_user_id', 'cancelled_by_user_id', 'dispute_opened_by_user_id'];
        foreach ($fields as $field) {
            if (($created || $subject->wasChanged($field)) && ($id = $subject->getAttribute($field))) {
                return Models\User::find($id);
            }
        }

        if ($created && ($subject instanceof Models\CareRequestApplication || $subject instanceof Models\CompletedExtraVisitRequest)) {
            return Models\User::find($subject->caregiver_user_id);
        }

        return null;
    }

    public function period(mixed $start, mixed $end, string $timezone): string
    {
        if (! $start) {
            return 'Time to be confirmed';
        }
        $start = Carbon::parse($start, config('app.timezone'))->setTimezone($timezone);
        $end = $end ? Carbon::parse($end, config('app.timezone'))->setTimezone($timezone) : null;

        return $start->format('D, M j, Y · g:i A T')
            .($end ? ' – '.$end->format($start->isSameDay($end) ? 'g:i A T' : 'D, M j, Y · g:i A T') : '')
            .' ('.$timezone.')';
    }
}
