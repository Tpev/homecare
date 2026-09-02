<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingSpendDaily extends Model
{
    protected $table = 'marketing_spend_daily';

    protected $fillable = [
        'spend_date',
        'channel',
        'campaign_id',
        'campaign_name',
        'ad_set_name',
        'ad_name',
        'spend_cents',
        'impressions',
        'clicks',
        'currency',
    ];

    protected $casts = [
        'spend_date' => 'date',
        'spend_cents' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];
}
