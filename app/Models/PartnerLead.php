<?php

namespace App\Models;

use App\Support\PartnerOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerLead extends Model
{
    protected $fillable = [
        'partner_profile_id', 'lead_id', 'source_lead_id', 'partner_lead_credit_id',
        'category', 'contact_name', 'contact_email', 'contact_phone', 'location', 'zip',
        'service_context', 'assigned_by_user_id', 'assigned_at', 'sharing_confirmed_at',
        'geography_confirmed_at',
    ];

    protected $hidden = ['source_lead_id', 'assigned_by_user_id'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'sharing_confirmed_at' => 'datetime',
            'geography_confirmed_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_profile_id');
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(PartnerLeadCredit::class, 'partner_lead_credit_id');
    }

    public function categoryLabel(): string
    {
        return PartnerOptions::categories()[$this->category] ?? $this->category;
    }
}
