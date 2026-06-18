<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class VoiceAiCall extends Model
{
    use HasFactory;

    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RINGING = 'ringing';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_BUSY = 'busy';

    public const STATUS_NO_ANSWER = 'no_answer';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'admin_user_id',
        'direction',
        'status',
        'to_phone',
        'from_phone',
        'twilio_call_sid',
        'twilio_account_sid',
        'twilio_status',
        'current_step',
        'gathered_name',
        'gathered_relationship',
        'gathered_phone',
        'gathered_location',
        'gathered_urgency',
        'gathered_callback_time',
        'gathered_care_needs',
        'callback_requested',
        'signup_link_requested',
        'transcript',
        'transcript_text',
        'summary',
        'raw_payload',
        'metadata',
        'last_error',
        'started_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'callback_requested' => 'boolean',
            'signup_link_requested' => 'boolean',
            'transcript' => 'array',
            'raw_payload' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function appendTranscript(string $speaker, string $text, ?Carbon $at = null): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $turns = is_array($this->transcript) ? $this->transcript : [];
        $turns[] = [
            'speaker' => $speaker,
            'text' => $text,
            'at' => ($at ?: now())->toISOString(),
        ];

        $this->forceFill([
            'transcript' => $turns,
            'transcript_text' => collect($turns)
                ->map(fn (array $turn): string => ucfirst((string) $turn['speaker']).': '.(string) $turn['text'])
                ->implode("\n"),
        ])->save();
    }
}
