<?php

namespace App\Services\Screening;

use App\Models\ScreeningAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ScreeningAuditService
{
    public function record(?int $caseId, ?int $actorId, string $type, array $payload = []): ScreeningAuditEvent
    {
        $event = ScreeningAuditEvent::create([
            'event_id' => (string) Str::uuid(), 'screening_case_id' => $caseId,
            'actor_user_id' => $actorId, 'event_type' => $type,
            // Actor and subject references survive nullable foreign-key deletion.
            'payload' => ['actor_id' => $actorId, ...$payload], 'occurred_at' => now(),
        ]);
        DB::afterCommit(function () use ($event): void {
            try {
                $this->exportPending($event->id);
            } catch (Throwable $error) {
                // The committed outbox row remains retryable by screening:export-audit.
                Log::error('Screening audit file delivery failed', ['event_id' => $event->event_id, 'error_type' => $error::class]);
            }
        });

        return $event;
    }

    public function exportPending(?int $onlyId = null): int
    {
        $directory = config('screening.audit_directory', rtrim(config('screening.private_directory', storage_path('app/private/screening')), '/\\').'/audit');
        $private = app(ScreeningPrivateFiles::class);
        $private->directory($directory);
        $count = 0;
        ScreeningAuditEvent::whereNull('exported_at')->when($onlyId, fn ($q) => $q->whereKey($onlyId))
            ->orderBy('id')->chunkById(250, function ($events) use ($directory, $private, &$count): void {
                foreach ($events->groupBy(fn ($event) => $event->occurred_at->utc()->format('Y-m-d')) as $date => $group) {
                    $file = $private->open($directory.DIRECTORY_SEPARATOR.$date.'.jsonl', 'c+b');
                    try {
                        if (! flock($file, LOCK_EX)) {
                            throw new RuntimeException('Cannot lock the screening audit file.');
                        }
                        $written = [];
                        while (true) {
                            $lineStart = ftell($file);
                            $line = fgets($file);
                            if ($line === false) {
                                break;
                            }
                            $decoded = json_decode($line, true);
                            if (! str_ends_with($line, "\n")) {
                                if (isset($decoded['event_id'])) {
                                    if (fwrite($file, "\n") !== 1 || ! fflush($file)) {
                                        throw new RuntimeException('Cannot complete the screening audit line.');
                                    }
                                } else {
                                    // A failed write is not a committed event. Preserve its bytes
                                    // separately before repairing only the incomplete trailing fragment.
                                    $recovery = $directory.DIRECTORY_SEPARATOR.$date.'-partial-'.hash('sha256', $line).'.recovery';
                                    $private->writeLocked($recovery, $line);
                                    if (! ftruncate($file, $lineStart) || ! fflush($file)) {
                                        throw new RuntimeException('Cannot recover an interrupted screening audit write.');
                                    }
                                    fseek($file, 0, SEEK_END);
                                    break;
                                }
                            }
                            if (! isset($decoded['event_id'])) {
                                throw new RuntimeException('Invalid complete screening audit line; manual recovery is required.');
                            }
                            if (isset($decoded['event_id'])) {
                                $written[$decoded['event_id']] = true;
                            }
                        }
                        foreach ($group as $event) {
                            if (! isset($written[$event->event_id])) {
                                $line = json_encode($this->serialize($event), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
                                if (fwrite($file, $line) !== strlen($line) || ! fflush($file)) {
                                    throw new RuntimeException('Incomplete screening audit file write.');
                                }
                                $written[$event->event_id] = true;
                            }
                            // An earlier attempt may have flushed a complete line but
                            // failed fsync. Deduplication must not acknowledge it yet.
                            $this->persistFile($file);
                            $event->update(['exported_at' => now()]);
                            $count++;
                        }
                    } finally {
                        flock($file, LOCK_UN);
                        fclose($file);
                    }
                }
            });

        return $count;
    }

    protected function persistFile($file): void
    {
        if (! fflush($file) || (function_exists('fsync') && ! fsync($file))) {
            throw new RuntimeException('Could not persist screening audit file.');
        }
    }

    public function serialize(ScreeningAuditEvent $event): array
    {
        return ['event_id' => $event->event_id, 'case_id' => $event->screening_case_id,
            'actor_user_id' => $event->actor_user_id, 'event_type' => $event->event_type,
            'occurred_at' => $event->occurred_at->utc()->toIso8601String(), 'payload' => $event->payload];
    }
}
