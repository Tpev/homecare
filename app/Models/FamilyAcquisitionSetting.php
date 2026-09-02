<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyAcquisitionSetting extends Model
{
    protected $fillable = [
        'alerts_enabled',
        'new_lead_alert_emails',
        'escalation_alert_emails',
        'first_call_sla_minutes',
        'updated_by_user_id',
    ];

    protected $casts = [
        'alerts_enabled' => 'boolean',
        'first_call_sla_minutes' => 'integer',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'alerts_enabled' => true,
            'first_call_sla_minutes' => 15,
        ]);
    }

    /** @return list<string> */
    public function newLeadRecipients(): array
    {
        return self::parseEmails($this->new_lead_alert_emails);
    }

    /** @return list<string> */
    public function escalationRecipients(): array
    {
        $configured = self::parseEmails($this->escalation_alert_emails);

        return $configured !== [] ? $configured : $this->newLeadRecipients();
    }

    /** @return list<string> */
    public static function parseEmails(?string $value): array
    {
        return collect(preg_split('/[,;\s]+/', (string) $value) ?: [])
            ->map(fn ($email): string => strtolower(trim((string) $email)))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function invalidEmails(?string $value): array
    {
        return collect(preg_split('/[,;\s]+/', (string) $value) ?: [])
            ->map(fn ($email): string => trim((string) $email))
            ->filter()
            ->reject(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->values()
            ->all();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
