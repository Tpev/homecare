<?php

namespace App\Models;

use App\Support\PartnerOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerProfile extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'user_id', 'business_name', 'website', 'description', 'contact_name',
        'contact_email', 'contact_phone', 'categories', 'coverage_states',
        'coverage_type', 'coverage_areas', 'customer_notes', 'status',
        'internal_notes', 'submitted_at', 'terms_accepted_at',
    ];

    protected $hidden = ['internal_notes'];

    protected $attributes = ['status' => self::STATUS_NEW];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'coverage_states' => 'array',
            'submitted_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return list<string> */
    public function categoryLabels(): array
    {
        return array_values(array_map(
            fn (string $code): string => PartnerOptions::categories()[$code] ?? $code,
            $this->categories ?? [],
        ));
    }

    /** @return list<string> */
    public function coverageStateLabels(): array
    {
        return array_values(array_map(
            fn (string $code): string => PartnerOptions::states()[$code] ?? $code,
            $this->coverage_states ?? [],
        ));
    }

    public function coverageSummary(): string
    {
        $states = implode(', ', $this->coverageStateLabels());

        return $this->coverage_type === 'entire_states'
            ? $states.' (statewide)'
            : implode(' - ', array_filter([$states, $this->coverage_areas]));
    }

    public function statusLabel(): string
    {
        return PartnerOptions::statuses()[$this->status] ?? ucfirst((string) $this->status);
    }
}
