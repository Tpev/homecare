<?php

namespace App\Livewire\Family;

use App\Models\AiRequestSession;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Services\AiCopilot\CopilotTurnService;
use App\Services\AiCopilot\DraftNormalizer;
use App\Services\AiCopilot\MissingFieldsResolver;
use App\Services\AiCopilot\PublishCareRequestService;
use App\Services\AiCopilot\QualityScorer;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AiRequestCopilot extends Component
{
    public string $sessionId = '';
    public string $input = '';
    public bool $isProcessing = false;
    public array $messages = [];
    public array $draft = [];
    public array $missingRequired = [];
    public int $qualityScore = 0;
    public string $status = AiRequestSession::STATUS_DRAFTING;
    public array $quickReplies = [];
    public array $qualityHints = [];
    public array $taskOptions = [];
    public array $usStates = [
        'AL' => 'Alabama', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado',
        'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky',
        'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan',
        'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
        'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon',
        'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
        'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia',
        'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
    ];

    public function mount(): void
    {
        abort_unless(config('features.ai_request_copilot') === true, 404);
        abort_unless(auth()->user()?->role === 'family', 403);

        $this->taskOptions = CareTask::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $session = AiRequestSession::query()->create([
            'family_user_id' => auth()->id(),
            'status' => AiRequestSession::STATUS_DRAFTING,
            'draft_json' => [
                'request_type' => CareRequest::TYPE_ONE_TIME,
                'state' => auth()->user()?->state ? strtoupper((string) auth()->user()->state) : 'NC',
                'city' => auth()->user()?->city,
                'preferred_response_hours' => 12,
                'include_third_party_contact' => false,
                'recipient' => [],
                'third_party_contact' => [],
                'task_ids' => [],
            ],
            'missing_required_json' => [],
            'quality_score' => 0,
        ]);

        $session->messages()->create([
            'role' => 'assistant',
            'content_text' => 'Describe the care you need in your own words. I will build the full request with you step by step.',
            'structured_json' => ['kind' => 'welcome'],
        ]);

        $this->sessionId = $session->id;
        $this->refreshFromSession($session);
    }

    public function send(): void
    {
        $this->validate([
            'input' => ['required', 'string', 'max:2000'],
        ]);

        $session = $this->session();
        $session->messages()->create([
            'role' => 'user',
            'content_text' => trim($this->input),
        ]);

        $this->input = '';
        $this->isProcessing = true;

        app(CopilotTurnService::class)->process($session->fresh());
        $this->refreshFromSession($this->session());
        $this->isProcessing = false;
    }

    public function useQuickReply(string $text): void
    {
        $this->input = $text;
        $this->send();
    }

    public function syncDraft(): void
    {
        $session = $this->session();
        $normalized = app(DraftNormalizer::class)->merge($session->draft_json ?? [], $this->draft);
        $missing = app(MissingFieldsResolver::class)->requiredMissing($normalized);
        $quality = app(QualityScorer::class)->score($normalized, $missing);

        $session->forceFill([
            'draft_json' => $normalized,
            'missing_required_json' => $missing,
            'quality_score' => $quality,
            'status' => $missing === [] ? AiRequestSession::STATUS_READY_FOR_REVIEW : AiRequestSession::STATUS_DRAFTING,
        ])->save();

        $this->refreshFromSession($session->fresh('messages'));
    }

    public function publish(): void
    {
        try {
            $careRequest = app(PublishCareRequestService::class)->publish($this->session());
            session()->flash('status', 'AI request published successfully.');
            $this->redirect(route('family.requests.show', $careRequest->id, false), navigate: true);
        } catch (ValidationException $e) {
            $this->addError('publish', $e->getMessage());
            $this->refreshFromSession($this->session());
        }
    }

    public function fallbackToManual(): void
    {
        session()->flash('status', 'Switched to manual request form.');
        $this->redirect(route('family.requests.create', absolute: false), navigate: true);
    }

    private function session(): AiRequestSession
    {
        return AiRequestSession::query()
            ->where('id', $this->sessionId)
            ->where('family_user_id', auth()->id())
            ->firstOrFail();
    }

    private function refreshFromSession(AiRequestSession $session): void
    {
        $session->loadMissing('messages');

        $this->messages = $session->messages
            ->sortBy('id')
            ->values()
            ->map(fn ($message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content_text,
                'structured' => $message->structured_json,
            ])
            ->all();

        $this->draft = is_array($session->draft_json) ? $session->draft_json : [];
        $this->missingRequired = is_array($session->missing_required_json) ? $session->missing_required_json : [];
        $this->qualityScore = (int) $session->quality_score;
        $this->status = (string) $session->status;

        $lastAssistant = collect($this->messages)
            ->reverse()
            ->first(fn (array $m) => $m['role'] === 'assistant');

        $turn = Arr::get($lastAssistant, 'structured.turn', []);
        $this->quickReplies = is_array(Arr::get($turn, 'quick_replies')) ? array_values(Arr::get($turn, 'quick_replies')) : [];
        $this->qualityHints = is_array(Arr::get($lastAssistant, 'structured.turn.quality_hints'))
            ? array_values(Arr::get($lastAssistant, 'structured.turn.quality_hints'))
            : [];
    }

    public function render()
    {
        return view('livewire.family.ai-request-copilot');
    }
}

