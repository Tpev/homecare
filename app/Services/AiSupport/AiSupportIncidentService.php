<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportAdminAuditEvent;
use App\Models\AiSupportIncident;
use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Notifications\NotificationChannels;
use App\Support\MarketplaceEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportIncidentService
{
    public function __construct(private readonly MarketplaceNotificationService $notifications) {}

    /** @param array<string,mixed> $safeMetadata */
    public function open(
        string $reasonCode,
        string $summary,
        ?string $controlKey = null,
        ?int $supportTicketId = null,
        array $safeMetadata = [],
        string $severity = AiSupportIncident::SEVERITY_CRITICAL,
    ): AiSupportIncident {
        $this->validateIdentifier($reasonCode, 'reason code');
        if ($controlKey !== null) {
            $this->validateIdentifier($controlKey, 'control key');
        }
        if (! in_array($severity, [AiSupportIncident::SEVERITY_CRITICAL, AiSupportIncident::SEVERITY_WARNING], true)) {
            throw ValidationException::withMessages(['incident' => 'Select a recognized incident severity.']);
        }
        $summary = trim($summary);
        if (mb_strlen($summary) < 5 || mb_strlen($summary) > 500) {
            throw ValidationException::withMessages(['incident' => 'Use a content-free incident summary between 5 and 500 characters.']);
        }

        $incident = DB::transaction(function () use ($reasonCode, $summary, $controlKey, $supportTicketId, $safeMetadata, $severity): AiSupportIncident {
            $existing = AiSupportIncident::query()
                ->where('status', AiSupportIncident::STATUS_OPEN)
                ->where('reason_code', $reasonCode)
                ->where('control_key', $controlKey)
                ->where('support_ticket_id', $supportTicketId)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }
            $now = now();
            $created = AiSupportIncident::query()->create([
                'id' => (string) Str::uuid(),
                'reason_code' => $reasonCode,
                'severity' => $severity,
                'status' => AiSupportIncident::STATUS_OPEN,
                'control_key' => $controlKey,
                'support_ticket_id' => $supportTicketId,
                'summary' => $summary,
                'safe_metadata' => $safeMetadata ?: null,
                'opened_at' => $now,
                'retain_until' => $now->copy()->addMonths((int) config('ai_support.incident_evidence_months', 24)),
            ]);
            AiSupportAdminAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_family' => 'incident',
                'action' => 'ai_support_incident_opened',
                'subject_type' => AiSupportIncident::class,
                'subject_id' => $created->id,
                'result' => 'succeeded',
                'reason_code' => $reasonCode,
                'reason' => $summary,
                'metadata' => ['control_key' => $controlKey, 'support_ticket_id' => $supportTicketId, 'severity' => $severity],
                'policy_version' => (string) config('ai_support.policy_version'),
                'retain_until' => $created->retain_until,
                'occurred_at' => $now,
                'created_at' => $now,
            ]);

            return $created;
        }, 3);

        $admins = User::query()->where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            try {
                $critical = $incident->severity === AiSupportIncident::SEVERITY_CRITICAL;
                $this->notifications->notify(
                    recipients: $admins,
                    eventKey: MarketplaceEvent::SUPPORT_TICKET_REPLY,
                    title: $critical ? 'Critical AI Support stop' : 'AI Support operational warning',
                    body: $critical
                        ? 'AI Support stopped a capability. Review the incident in Admin before restoring it.'
                        : 'AI Support monitoring detected a warning. Review it in Admin.',
                    url: route('admin.ai-support.index'),
                    payload: ['ai_support_incident_id' => $incident->id, 'reason_code' => $reasonCode],
                    subject: null,
                    dedupeKey: 'ai-support-incident-'.$incident->id,
                    channelOverrides: [
                        NotificationChannels::EMAIL => true,
                        NotificationChannels::IN_APP => true,
                    ],
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $incident;
    }

    public function resolve(User $actor, AiSupportIncident $incident, string $reason): AiSupportIncident
    {
        if (! $actor->canManageAiSupportControls()) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['resolutionReason' => 'Enter a resolution reason between 5 and 500 characters.']);
        }

        return DB::transaction(function () use ($actor, $incident, $reason): AiSupportIncident {
            $locked = AiSupportIncident::query()->lockForUpdate()->findOrFail($incident->id);
            if ($locked->status === AiSupportIncident::STATUS_RESOLVED) {
                return $locked;
            }
            $now = now();
            $locked->forceFill([
                'status' => AiSupportIncident::STATUS_RESOLVED,
                'resolved_at' => $now,
                'resolved_by_user_id' => $actor->id,
                'resolution_reason' => $reason,
            ])->save();
            AiSupportAdminAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_family' => 'incident',
                'action' => 'ai_support_incident_resolved',
                'actor_user_id' => $actor->id,
                'subject_type' => AiSupportIncident::class,
                'subject_id' => $locked->id,
                'result' => 'succeeded',
                'reason_code' => 'resolved',
                'reason' => $reason,
                'metadata' => ['original_reason_code' => $locked->reason_code],
                'policy_version' => (string) config('ai_support.policy_version'),
                'retain_until' => $locked->retain_until,
                'occurred_at' => $now,
                'created_at' => $now,
            ]);

            return $locked->fresh();
        }, 3);
    }

    private function validateIdentifier(string $value, string $label): void
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,119}\z/', $value) !== 1) {
            throw ValidationException::withMessages(['incident' => "Use a compact {$label}."]);
        }
    }
}
