<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareRecipient extends Model
{
    use HasFactory;

    protected $table = 'care_request_recipients';

    protected $fillable = [
        'care_request_id',
        'recipient_is_requester',
        'full_name',
        'date_of_birth',
        'gender',
        'mobility_level',
        'relationship_to_family',
        'care_notes',
        'care_recipient_profile_id',
        'care_recipient_profile_version_id',
    ];

    protected function casts(): array
    {
        return [
            'recipient_is_requester' => 'boolean',
        ];
    }

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }

    public function careRecipientProfile(): BelongsTo
    {
        return $this->belongsTo(CareRecipientProfile::class);
    }

    public function careRecipientProfileVersion(): BelongsTo
    {
        return $this->belongsTo(CareRecipientProfileVersion::class);
    }

    public function recipientContextLabel(): string
    {
        return $this->receivesCareAsRequester()
            ? 'Requester receives care'
            : 'Family member receives care';
    }

    public function recipientContextDescription(): string
    {
        if ($this->receivesCareAsRequester()) {
            return 'The person posting is also receiving care.';
        }

        $relationship = trim((string) $this->relationship_to_family);

        return $relationship !== ''
            ? 'A family contact is coordinating care for their '.strtolower($relationship).'.'
            : 'A family contact is coordinating care for someone else.';
    }

    public function receivesCareAsRequester(): bool
    {
        return (bool) $this->recipient_is_requester
            || strcasecmp((string) $this->relationship_to_family, 'self') === 0;
    }
}
