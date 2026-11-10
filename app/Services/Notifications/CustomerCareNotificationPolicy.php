<?php

namespace App\Services\Notifications;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/** Durable, request-scoped silence, including queued deliveries and future plan visits. */
class CustomerCareNotificationPolicy
{
    public function suppresses(User $recipient, ?Model $subject = null, array $payload = []): bool
    {
        if ($recipient->role !== 'family') {
            return false;
        }
        $visited = [];
        if ($subject && $this->managed($subject, $visited)) {
            return true;
        }
        foreach (['care_request_id' => CareRequest::class, 'care_booking_id' => CareBooking::class,
            'care_plan_id' => CarePlan::class, 'conversation_id' => CareRequestConversation::class] as $key => $class) {
            if (isset($payload[$key]) && ($model = $class::find($payload[$key])) && $this->managed($model, $visited)) {
                return true;
            }
        }
        // Older queued emails may contain only their tracking identity.
        if ($deliveryId = data_get($payload, 'tracking.delivery_id')) {
            $delivery = \App\Models\MarketplaceNotificationDelivery::whereKey($deliveryId)->where('user_id', $recipient->id)->first();
            if ($delivery?->notifiable_type && $delivery->notifiable_id) {
                $payload['customer_care_subject_type'] = $delivery->notifiable_type;
                $payload['customer_care_subject_id'] = $delivery->notifiable_id;
            }
        }
        $type = $payload['customer_care_subject_type'] ?? null;
        $id = $payload['customer_care_subject_id'] ?? null;
        $class = is_string($type) ? (Relation::getMorphedModel($type) ?? $type) : null;
        if ($id && $class && is_subclass_of($class, Model::class)
            && str_starts_with($class, 'App\\Models\\') && ($model = $class::find($id))) {
            return $this->managed($model, $visited);
        }

        return false;
    }

    private function managed(Model $model, array &$visited, int $depth = 0): bool
    {
        $key = $model::class.':'.$model->getKey();
        if (isset($visited[$key]) || $depth > 6) {
            return false;
        }
        $visited[$key] = true;
        if ($model instanceof CareRequest && (bool) CareRequest::whereKey($model->id)->value('staff_managed')) {
            return true;
        }
        foreach (['careRequest', 'booking', 'carePlan', 'plan', 'sourceCareRequest', 'conversation', 'invitation', 'shift', 'ticket'] as $name) {
            if (! method_exists($model, $name)) {
                continue;
            }
            $relation = $model->{$name}();
            // Always read current state: a queued job may hold a stale relation.
            if ($relation instanceof BelongsTo && ($related = $relation->first()) && $this->managed($related, $visited, $depth + 1)) {
                return true;
            }
        }

        return false;
    }
}
