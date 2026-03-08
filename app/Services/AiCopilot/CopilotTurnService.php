<?php

namespace App\Services\AiCopilot;

use App\Contracts\AiCopilotResponder;
use App\Models\AiRequestMessage;
use App\Models\AiRequestSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CopilotTurnService
{
    public function __construct(
        private readonly AiCopilotResponder $responder,
        private readonly RuleBasedCopilotResponder $heuristicResponder,
        private readonly DraftNormalizer $normalizer,
        private readonly MissingFieldsResolver $missingFieldsResolver,
        private readonly QualityScorer $qualityScorer,
        private readonly SafetyGuard $safetyGuard,
    ) {
    }

    /**
     * @return array{
     *   draft:array<string,mixed>,
     *   missing:array<int,string>,
     *   quality_score:int,
     *   status:string,
     *   assistant_message:string,
     *   quick_replies:array<int,string>,
     *   quality_hints:array<int,string>,
     *   safety_flags:array<int,string>
     * }
     */
    public function process(AiRequestSession $session): array
    {
        $conversation = $session->messages()
            ->orderBy('id')
            ->get(['role', 'content_text'])
            ->map(fn (AiRequestMessage $message) => [
                'role' => $message->role,
                'content' => (string) $message->content_text,
            ])
            ->all();

        $currentDraft = is_array($session->draft_json) ? $session->draft_json : [];
        $missingBefore = $this->missingFieldsResolver->requiredMissing($currentDraft);
        $turn = $this->responder->generate($conversation, $currentDraft, $missingBefore);
        $heuristicTurn = $this->heuristicResponder->generate($conversation, $currentDraft, $missingBefore);

        $mergedUpdates = $this->mergeUpdates(
            is_array($heuristicTurn['field_updates'] ?? null) ? $heuristicTurn['field_updates'] : [],
            is_array($turn['field_updates'] ?? null) ? $turn['field_updates'] : []
        );

        $mergedDraft = $this->normalizer->merge($currentDraft, $mergedUpdates);
        $mergedDraft = $this->applyAutomaticTitle($mergedDraft);

        $safetyFlags = collect($turn['safety_flags'] ?? [])
            ->merge($this->safetyGuard->flagsForText((string) data_get($conversation, count($conversation) - 1 . '.content', '')))
            ->unique()
            ->values()
            ->all();

        $qualityHints = is_array($turn['quality_hints'] ?? null) ? array_values($turn['quality_hints']) : [];
        if (in_array('medical_scope', $safetyFlags, true)) {
            $qualityHints[] = $this->safetyGuard->safetyHint();
        }

        $missingAfter = $this->missingFieldsResolver->requiredMissing($mergedDraft);
        $qualityScore = $this->qualityScorer->score($mergedDraft, $missingAfter);
        $status = $missingAfter === [] ? AiRequestSession::STATUS_READY_FOR_REVIEW : AiRequestSession::STATUS_DRAFTING;

        $assistantMessage = trim((string) ($turn['assistant_message'] ?? ''));
        if ($assistantMessage === '' || $assistantMessage === 'Got it. Tell me the next detail.') {
            $assistantMessage = trim((string) ($heuristicTurn['assistant_message'] ?? ''));
        }
        $lastAssistantMessage = $this->latestAssistantMessage($conversation);
        $aiUpdatesEmpty = (is_array($turn['field_updates'] ?? null) ? $turn['field_updates'] : []) === [];
        $heuristicHasUpdates = (is_array($heuristicTurn['field_updates'] ?? null) ? $heuristicTurn['field_updates'] : []) !== [];
        if ($aiUpdatesEmpty && $heuristicHasUpdates && ($assistantMessage === $lastAssistantMessage || str_contains(Str::lower($assistantMessage), 'title'))) {
            $assistantMessage = trim((string) ($heuristicTurn['assistant_message'] ?? $assistantMessage));
        }

        if ($assistantMessage === '') {
            $assistantMessage = $missingAfter === []
                ? 'Great, your request is ready for review. Confirm and publish when ready.'
                : 'Got it. I captured that. Let me ask the next detail.';
        }

        if (in_array('medical_scope', $safetyFlags, true)) {
            $assistantMessage .= ' '.$this->safetyGuard->safetyHint();
        }

        $session->forceFill([
            'draft_json' => $mergedDraft,
            'missing_required_json' => $missingAfter,
            'quality_score' => $qualityScore,
            'status' => $status,
            'model' => $turn['model'] ?? $session->model,
            'last_ai_at' => now(),
        ])->save();

        $session->messages()->create([
            'role' => 'assistant',
            'content_text' => $assistantMessage,
            'structured_json' => [
                'turn' => $turn,
                'heuristic_turn' => $heuristicTurn,
                'missing_after' => $missingAfter,
                'quality_score' => $qualityScore,
                'safety_flags' => $safetyFlags,
            ],
            'latency_ms' => $turn['latency_ms'] ?? null,
            'prompt_tokens' => $turn['prompt_tokens'] ?? null,
            'completion_tokens' => $turn['completion_tokens'] ?? null,
        ]);

        return [
            'draft' => $mergedDraft,
            'missing' => $missingAfter,
            'quality_score' => $qualityScore,
            'status' => $status,
            'assistant_message' => $assistantMessage,
            'quick_replies' => is_array($turn['quick_replies'] ?? null) ? array_values($turn['quick_replies']) : [],
            'quality_hints' => $qualityHints,
            'safety_flags' => $safetyFlags,
        ];
    }

    /**
     * @param  array<string,mixed>  $heuristic
     * @param  array<string,mixed>  $ai
     * @return array<string,mixed>
     */
    private function mergeUpdates(array $heuristic, array $ai): array
    {
        return array_replace_recursive($heuristic, $ai);
    }

    /**
     * @param  array<string,mixed>  $draft
     * @return array<string,mixed>
     */
    private function applyAutomaticTitle(array $draft): array
    {
        $title = trim((string) Arr::get($draft, 'title', ''));
        if ($title !== '' && ! $this->isWeakTitle($title)) {
            return $draft;
        }

        $tasks = Arr::get($draft, 'tasks', []);
        $relationship = trim((string) Arr::get($draft, 'recipient.relationship_to_family', ''));
        $city = trim((string) Arr::get($draft, 'city', ''));
        $type = (string) Arr::get($draft, 'request_type', '');

        if (! is_array($tasks) || $tasks === []) {
            return $draft;
        }

        $firstTask = Str::title((string) $tasks[0]);
        $title = $firstTask.' support';
        if (count($tasks) > 1) {
            $title = $firstTask.' + '.Str::title((string) $tasks[1]).' support';
        }

        if ($relationship !== '') {
            $title .= ' for '.$relationship;
        }

        if ($type !== '') {
            $title .= $type === 'recurring' ? ' (recurring)' : ' (one-time)';
        }

        if ($city !== '') {
            $title .= ' in '.$city;
        }

        $draft['title'] = Str::limit($title, 140, '');
        return $draft;
    }

    /**
     * @param  array<int,array{role:string,content:string}>  $conversation
     */
    private function latestAssistantMessage(array $conversation): string
    {
        for ($i = count($conversation) - 1; $i >= 0; $i--) {
            if (($conversation[$i]['role'] ?? '') === 'assistant') {
                return trim((string) ($conversation[$i]['content'] ?? ''));
            }
        }

        return '';
    }

    private function isWeakTitle(string $title): bool
    {
        $value = Str::lower(trim($title));
        return $value === ''
            || in_array($value, ['not really', "i don't know", 'dont know', 'idk', 'whatever', 'test', 'testestest'], true)
            || str_contains($value, 'what');
    }
}
