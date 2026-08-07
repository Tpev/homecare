<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamilyAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyRecipientProfile extends Model
{
    use BelongsToFamilyAccount, HasFactory;

    protected $fillable = [
        'family_account_id',
        'family_user_id',
        'recipient_is_requester',
        'full_name',
        'date_of_birth',
        'gender',
        'mobility_level',
        'relationship_to_family',
        'care_notes',
        'include_third_party_contact',
        'third_party_full_name',
        'third_party_relationship_to_recipient',
        'third_party_phone',
        'third_party_email',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'recipient_is_requester' => 'boolean',
            'include_third_party_contact' => 'boolean',
        ];
    }

    public function familyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }
}
