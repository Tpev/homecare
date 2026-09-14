<?php

namespace App\Livewire\Admin;

use App\Jobs\SendLeadWelcomeEmail;
use App\Mail\FamilyLeadWelcomeMail;
use App\Models\FamilyAcquisitionSetting;
use App\Models\Lead;
use App\Models\LeadWelcomeEmail;
use App\Services\FamilyAcquisition\LeadWelcomeService;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class WelcomeEmailPanel extends Component
{
    #[Reactive]
    public string $range = 'all';

    #[Reactive]
    public string $campaign = 'all';

    public bool $editing = false;

    public bool $enabled = false;

    public string $subject = '';

    public string $body = '';

    public string $replyTo = '';

    public string $feedback = '';

    public bool $showTestForm = false;

    public string $testRecipient = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->loadSettings();
        $this->testRecipient = (string) auth()->user()->email;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }

    public function loadSettings(): void
    {
        $this->authorizeAdmin();
        $settings = FamilyAcquisitionSetting::current();
        $this->enabled = (bool) $settings->welcome_email_enabled;
        $this->subject = $settings->welcome_email_subject ?: LeadWelcomeService::SUBJECT;
        $this->body = $settings->welcome_email_body ?: LeadWelcomeService::BODY;
        $this->replyTo = $settings->welcome_email_reply_to ?: (string) config('mail.from.address');
        $this->editing = false;
        $this->resetValidation();
    }

    private function validatedContent(): array
    {
        return $this->validate([
            'enabled' => 'boolean',
            'subject' => ['required', 'string', 'max:180', 'not_regex:/[\r\n]/'],
            'body' => 'required|string|min:20|max:3000',
            'replyTo' => 'required|email|max:255',
        ]);
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        $data = $this->validatedContent();
        FamilyAcquisitionSetting::current()->update([
            'welcome_email_enabled' => $data['enabled'],
            'welcome_email_subject' => trim($data['subject']),
            'welcome_email_body' => trim($data['body']),
            'welcome_email_reply_to' => trim($data['replyTo']),
            'updated_by_user_id' => auth()->id(),
        ]);
        $this->editing = false;
        $this->feedback = $this->enabled ? 'Saved. Automatic email is on.' : 'Saved. Automatic email is paused.';
        if ($this->enabled && app(LeadWelcomeService::class)->capturesMail()) {
            $this->feedback = 'Saved. Local preview only.';
        }
    }

    public function sendTest(): void
    {
        $this->authorizeAdmin();
        $this->showTestForm = true;
        $this->feedback = '';
        $this->resetValidation('test');
        $this->testRecipient = trim($this->testRecipient);
        $this->validate([
            'testRecipient' => ['required', 'email', 'max:255', 'not_regex:/[\r\n]/'],
        ], [], ['testRecipient' => 'test recipient']);
        $this->validatedContent();
        $service = app(LeadWelcomeService::class);
        try {
            Mail::mailer($service->mailer())->to($this->testRecipient)->send($this->previewMail(true));
            $this->feedback = $service->capturesMail()
                ? 'Test for '.$this->testRecipient.' captured locally.'
                : 'Test email sent to '.$this->testRecipient.'.';
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('test', 'The test could not be sent. Check your mail configuration.');
        }
    }

    public function retry(int $id): void
    {
        $this->authorizeAdmin();
        $message = LeadWelcomeEmail::findOrFail($id);
        if ($message->status !== 'failed' || ! $message->dedupe_key || $message->last_attempt_at || $message->sent_at || $message->previewed_at) {
            return;
        }
        if ($reason = app(LeadWelcomeService::class)->skipReason($message, FamilyAcquisitionSetting::current())) {
            $this->feedback = 'Cannot retry: '.$reason;

            return;
        }
        if (LeadWelcomeEmail::whereKey($id)->where('status', 'failed')->whereNull('last_attempt_at')
            ->whereNull('sent_at')->whereNull('previewed_at')->update(['status' => 'queued', 'reason' => null])) {
            try {
                SendLeadWelcomeEmail::dispatch($id);
                $this->feedback = 'Retry queued for '.$message->lead->name.'.';
            } catch (\Throwable $exception) {
                LeadWelcomeEmail::whereKey($id)->where('status', 'queued')->whereNull('last_attempt_at')
                    ->update(['status' => 'failed', 'reason' => 'Could not queue the email.']);
                report($exception);
                $this->addError('test', 'The queue is unavailable. Try again after it has recovered.');
            }
        }
    }

    private function previewMail(bool $test = false): FamilyLeadWelcomeMail
    {
        return new FamilyLeadWelcomeMail('Sarah', ($test ? '[Test] ' : '').$this->subject, $this->body,
            $test ? route('register') : '#', $test ? route('register') : '#', $this->replyTo);
    }

    public function render()
    {
        $this->authorizeAdmin();
        $leads = Lead::facebook()->when(in_array($this->range, ['30', '60', '90']), function ($query) {
            $query->whereRaw('COALESCE(submitted_at, created_at) >= ?', [now()->subDays((int) $this->range - 1)->startOfDay()]);
        });
        if ($this->campaign !== 'all') {
            $leads->where(function ($query) {
                $query->where('data->meta->campaign_id', $this->campaign)->orWhere('data->facebook->campaign_id', $this->campaign);
            });
        }
        $messages = LeadWelcomeEmail::whereIn('lead_id', (clone $leads)->select('id'));

        return view('livewire.admin.welcome-email-panel', [
            'savedEnabled' => (bool) FamilyAcquisitionSetting::current()->welcome_email_enabled,
            'localCapture' => app(LeadWelcomeService::class)->capturesMail(),
            'previewHtml' => $this->previewMail()->render(),
            'counts' => [
                'all' => (clone $leads)->count(),
                'sent' => (clone $messages)->whereNotNull('sent_at')->count(),
                'account' => (clone $messages)->whereNotNull('account_created_at')->distinct()->count('account_user_id'),
                'request' => (clone $messages)->whereNotNull('request_posted_at')->distinct()->count('care_request_id'),
                'previewed' => (clone $messages)->whereNotNull('previewed_at')->count(),
            ],
            'attention' => (clone $messages)->where(fn ($q) => $q->whereIn('status', ['failed', 'retrying', 'unconfirmed'])->orWhere(fn ($q) => $q->whereIn('status', ['sending', 'queued'])->where('updated_at', '<', now()->subMinutes(5))))->with('lead')->get(),
        ]);
    }
}
