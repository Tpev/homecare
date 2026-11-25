<?php

namespace App\Models\Concerns;

use App\Models\CarePlan;
use App\Models\CarePricingAgreement;
use App\Models\CareRequest;
use App\Support\MarketplacePricing;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Persist the program offered when work is created, independently of later enrollment changes. */
trait HasCareAssistancePricing
{
    protected static function bootHasCareAssistancePricing(): void
    {
        static::creating(function ($model): void {
            if (array_key_exists('care_assistance_pricing_agreement_id', $model->getAttributes())) {
                // Replicated shared-care requests retain their source terms, including NULL.
                if ($model->care_assistance_pricing_agreement_id) {
                    CarePricingAgreement::query()->whereKey($model->care_assistance_pricing_agreement_id)
                        ->where('family_account_id', $model->family_account_id)
                        ->where('program', CarePricingAgreement::CARE_ASSISTANCE)->firstOrFail();
                }

                return;
            }

            if ($model instanceof CareRequest && $model->care_plan_id) {
                $source = CarePlan::query()->where('family_account_id', $model->family_account_id)->findOrFail($model->care_plan_id);
                $agreementId = $source->care_assistance_pricing_agreement_id;
            } elseif ($model instanceof CarePlan && $model->source_care_request_id) {
                $source = CareRequest::query()->where('family_account_id', $model->family_account_id)->findOrFail($model->source_care_request_id);
                $agreementId = $source->care_assistance_pricing_agreement_id;
            } else {
                $agreementId = app(MarketplacePricing::class)->activeCareAssistance((int) $model->family_account_id)?->id;
            }
            $model->care_assistance_pricing_agreement_id = $agreementId;
        });
    }

    public function careAssistancePricingAgreement(): BelongsTo
    {
        return $this->belongsTo(CarePricingAgreement::class, 'care_assistance_pricing_agreement_id');
    }

    public function isCareAssistance(): bool
    {
        if ($this->exists && ! array_key_exists('care_assistance_pricing_agreement_id', $this->getAttributes())) {
            $this->care_assistance_pricing_agreement_id = $this->newQuery()->whereKey($this->id)->value('care_assistance_pricing_agreement_id');
        }

        return $this->care_assistance_pricing_agreement_id !== null;
    }
}
