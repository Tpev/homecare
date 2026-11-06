<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Messaging\LeadSmsService;
use App\Services\Screening\CaregiverWorkEligibility;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LeadSmsComposer extends Component
{
    #[Locked]
    public ?int $leadId = null;

    #[Locked]
    public ?int $caregiverUserId = null;

    private Lead|User $recipient;

    #[Locked]
    public string $requestKey;

    #[Locked]
    public ?int $editingTemplateId = null;

    #[Locked]
    public ?int $pendingMessageId = null;

    public bool $showComposer = false;

    public bool $showTemplates = false;

    public string $smsBody = '';

    public string $messageMode = 'free_text';

    public string $selectedTemplateId = '';

    public string $templateName = '';

    public string $templateBody = '';

    public string $notice = '';

    public function mount(?int $leadId = null, ?int $caregiverUserId = null): void
    {
        abort_if(($leadId === null) === ($caregiverUserId === null), 404);
        $this->leadId = $leadId;
        $this->caregiverUserId = $caregiverUserId;
        $this->requestKey = (string) Str::uuid();
    }

    public function booted(): void
    {
        $actor = auth()->user()?->fresh();
        if ($this->caregiverUserId !== null) {
            abort_unless($actor?->canAccessCaregiverCrm(), 403);
            $caregiver = app(CaregiverWorkEligibility::class)->caregivers()->find($this->caregiverUserId);
            abort_unless($caregiver, 404, 'This caregiver is no longer available in the CRM.');
            $this->recipient = $caregiver;
        } else {
            abort_unless($actor?->canAccessCrm(), 403);
            $this->recipient = Lead::findOrFail($this->leadId);
        }
    }

    public function startMessage(): void
    {
        if ($this->pendingMessageId) {
            $this->refreshPendingMessage();
            if ($this->pendingMessageId) {
                return;
            }
        }
        $this->resetValidation();
        $this->reset('smsBody', 'messageMode', 'selectedTemplateId', 'notice');
        $this->requestKey = (string) Str::uuid();
        $this->showComposer = true;
    }

    public function updatedMessageMode(): void
    {
        if ($this->messageMode === 'free_text') {
            $this->reset('selectedTemplateId');
            $this->resetValidation('selectedTemplateId');
        }
    }

    public function updatedSelectedTemplateId(): void
    {
        $this->resetValidation('selectedTemplateId');
        if ($this->selectedTemplateId === '') {
            return;
        }

        $template = SmsTemplate::find($this->selectedTemplateId);
        if (! $template) {
            $this->addError('selectedTemplateId', 'This template was deleted. Choose another template or write a message.');

            return;
        }
        $this->smsBody = $template->body;
    }

    public function sendMessage(LeadSmsService $service): void
    {
        $this->resetValidation();
        $message = $service->send($this->recipient, auth()->user(), $this->smsBody, $this->requestKey);
        $this->dispatch($this->caregiverUserId ? 'caregiver-sms-updated' : 'lead-sms-updated');

        $this->showSendResult($message);
    }

    public function refreshPendingMessage(): void
    {
        if (! $this->pendingMessageId) {
            return;
        }
        $message = SmsMessage::query()->where($this->caregiverUserId ? 'caregiver_user_id' : 'lead_id', $this->recipient->id)
            ->where('sent_by_user_id', auth()->id())->findOrFail($this->pendingMessageId);
        if (! $message->awaitingProviderConfirmation()) {
            $this->resetValidation('send');
            $this->showSendResult($message);
            $this->dispatch($this->caregiverUserId ? 'caregiver-sms-updated' : 'lead-sms-updated');
        }
    }

    private function showSendResult(SmsMessage $message): void
    {
        if ($message->awaitingProviderConfirmation()) {
            $this->pendingMessageId = $message->id;
            $this->requestKey = $message->request_key;
            $this->notice = 'Awaiting confirmation. Please wait before sending this text again.';

            return;
        }
        $this->pendingMessageId = null;
        $this->notice = '';

        if (in_array($message->status, [SmsMessage::STATUS_FAILED, SmsMessage::STATUS_UNDELIVERED], true)) {
            $this->addError('send', 'The text could not be sent. '.$message->error_message);
            $this->requestKey = (string) Str::uuid();

            return;
        }

        $this->notice = 'Text submitted to '.$message->to_phone.'. Delivery status appears in the timeline.';
        $this->reset('smsBody', 'selectedTemplateId');
        $this->showComposer = false;
    }

    public function newTemplate(): void
    {
        $this->resetValidation();
        $this->reset('editingTemplateId', 'templateName', 'templateBody');
        $this->showTemplates = true;
    }

    public function editTemplate(int $id): void
    {
        $template = SmsTemplate::findOrFail($id);
        $this->resetValidation();
        $this->editingTemplateId = $template->id;
        $this->templateName = $template->name;
        $this->templateBody = $template->body;
        $this->showTemplates = true;
    }

    public function saveTemplate(): void
    {
        $this->templateName = trim($this->templateName);
        $this->templateBody = trim($this->templateBody);
        $this->validate([
            'templateName' => ['required', 'string', 'max:120'],
            'templateBody' => ['required', 'string', 'max:1600'],
        ]);

        $template = $this->editingTemplateId ? SmsTemplate::findOrFail($this->editingTemplateId) : new SmsTemplate;
        $template->fill([
            'name' => $this->templateName,
            'body' => $this->templateBody,
            'updated_by_user_id' => auth()->id(),
        ])->save();
        $this->newTemplate();
        $this->notice = 'SMS template saved.';
    }

    public function deleteTemplate(int $id): void
    {
        SmsTemplate::findOrFail($id)->delete();
        if ($this->editingTemplateId === $id) {
            $this->newTemplate();
        }
        if ($this->selectedTemplateId === (string) $id) {
            $this->selectedTemplateId = '';
        }
        $this->notice = 'SMS template deleted. Previously sent messages are unchanged.';
    }

    public function render(): View
    {
        return view('livewire.admin.lead-sms-composer', [
            'phone' => $this->recipient->phone,
            'doNotContact' => $this->recipient instanceof Lead && $this->recipient->do_not_contact_at,
            'senderPhone' => config('services.twilio.sms_from'),
            'templates' => SmsTemplate::orderBy('name')->orderBy('id')->get(),
        ]);
    }
}
