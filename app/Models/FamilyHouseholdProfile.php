<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamilyAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyHouseholdProfile extends Model
{
    use BelongsToFamilyAccount, HasFactory;

    protected $fillable = [
        'family_account_id',
        'family_user_id',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'zip',
        'home_access_notes',
        'time_expectations',
        'preferred_response_hours',
    ];

    public function familyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }
}
