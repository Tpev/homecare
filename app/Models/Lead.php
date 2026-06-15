<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    public const TYPE_FAMILY = 'family';
    public const TYPE_REFERRAL = 'referral';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const FAMILY_STAGES = [
        'new' => 'New',
        'contacted' => 'Reached out',
        'qualified' => 'Qualified',
        'intake_scheduled' => 'Intake scheduled',
        'converted' => 'Converted',
        'lost' => 'Lost',
        'closed' => 'Closed',
    ];

    public const REFERRAL_STAGES = [
        'new' => 'New source',
        'outreach' => 'Outreach',
        'meeting_scheduled' => 'Meeting scheduled',
        'active_referral' => 'Active referral source',
        'nurturing' => 'Nurturing',
        'not_fit' => 'Not a fit',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'lead_type',
        'name',
        'email',
        'phone',
        'company',
        'location',
        'zip',
        'data',
        'status',
        'assigned_admin_id',
        'priority',
        'source',
        'source_detail',
        'contact_role',
        'last_contacted_at',
        'next_follow_up_at',
        'converted_at',
        'closed_reason',
        'source_url',
        'referrer_url',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'data' => 'array',
        'last_contacted_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('occurred_at')->latest();
    }

    public function isReferralPipeline(): bool
    {
        return in_array($this->lead_type, [self::TYPE_REFERRAL, 'pcp', 'case_manager'], true);
    }

    public function pipelineLabel(): string
    {
        return $this->isReferralPipeline() ? 'Referral source' : 'Family lead';
    }

    /** @return array<string, string> */
    public function stageOptions(): array
    {
        return $this->isReferralPipeline() ? self::REFERRAL_STAGES : self::FAMILY_STAGES;
    }

    public function stageLabel(): string
    {
        return $this->stageOptions()[$this->status] ?? str((string) $this->status)->replace('_', ' ')->title()->toString();
    }

    public function sourceLabel(): string
    {
        $source = $this->source ?: data_get($this->data, 'source');

        return $source
            ? str((string) $source)->replace('_', ' ')->title()->toString()
            : 'Unknown source';
    }
}
