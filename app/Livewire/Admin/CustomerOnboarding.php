<?php

namespace App\Livewire\Admin;

use App\Exceptions\Payments\PaymentException;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\Lead;
use App\Services\CustomerOnboarding\CustomerOnboardingAccountService;
use App\Services\CustomerOnboarding\CustomerOnboardingDirectory;
use App\Services\CustomerOnboarding\FamilyPaymentSetupService;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CustomerOnboarding extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $filter = 'all';

    #[Url(as: 'family', except: '')]
    public string $selectedKey = '';

    public bool $showCreate = false;

    #[Locked]
    public ?int $creatingForLeadId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $note = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'filter');
        $this->resetPage();
    }

    public function openFamily(string $key): void
    {
        $row = app(CustomerOnboardingDirectory::class)->find($key);
        $this->selectedKey = $row['key'];
        $this->reset('note', 'showCreate', 'creatingForLeadId');
        $this->resetValidation();

        if ($row['account_id'] && $row['account_status'] !== 'closed') {
            $this->refreshPaymentStatus();
        }
    }

    public function closeFamily(): void
    {
        $this->reset('selectedKey', 'note', 'showCreate', 'creatingForLeadId');
        $this->resetValidation();
    }

    public function startCreate(): void
    {
        $this->reset('name', 'email', 'phone', 'creatingForLeadId');
        $this->resetValidation();
        if ($this->selectedKey !== '') {
            $row = app(CustomerOnboardingDirectory::class)->find($this->selectedKey);
            if (! $row['account_id']) {
                $this->creatingForLeadId = $row['lead_id'];
                $this->name = (string) $row['name'];
                $this->email = (string) $row['email'];
                $this->phone = (string) $row['phone'];
            }
        }
        $this->showCreate = true;
    }

    public function cancelCreate(): void
    {
        $this->reset('showCreate', 'creatingForLeadId', 'name', 'email', 'phone');
        $this->resetValidation();
    }

    public function createFamily(CustomerOnboardingAccountService $accounts): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $account = $accounts->create(auth()->user(), $data, $this->creatingForLeadId);
        $this->cancelCreate();
        $this->selectedKey = 'account:'.$account->id;
        session()->flash('onboarding_status', 'Family account ready. You can now prepare their payment link.');
    }

    public function generatePaymentLink(FamilyPaymentSetupService $payments): void
    {
        try {
            $payments->generate(auth()->user(), $this->selectedAccount());
            session()->flash('onboarding_status', 'Payment link ready to share. It expires in seven days.');
        } catch (PaymentException $exception) {
            report($exception);
            $this->addError('payment', $exception->userMessage);
        }
    }

    public function revokePaymentLink(FamilyPaymentSetupService $payments): void
    {
        $summary = $payments->summaryForAccount($this->selectedAccount());
        if ($summary['link']) {
            try {
                $payments->revoke(auth()->user(), $summary['link']);
                session()->flash('onboarding_status', 'Payment link revoked.');
            } catch (PaymentException $exception) {
                report($exception);
                $this->addError('payment', $exception->userMessage);
            }
        }
    }

    public function refreshPaymentStatus(): void
    {
        $this->resetValidation('payment');
        try {
            app(FamilyPaymentSetupService::class)->summaryForAccount($this->selectedAccount(), true);
        } catch (PaymentException $exception) {
            report($exception);
            $this->addError('payment', 'Payment status could not be checked. Please try again.');
        }
    }

    public function sendAccessEmail(CustomerOnboardingAccountService $accounts): void
    {
        $status = $accounts->sendAccessEmail(auth()->user(), $this->selectedAccount());
        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('onboarding_status', 'Account access email sent. The family can choose their password.');
        } else {
            $this->addError('access', __($status));
        }
    }

    public function addNote(): void
    {
        $this->validate(['note' => ['required', 'string', 'max:2000']]);
        $row = app(CustomerOnboardingDirectory::class)->find($this->selectedKey);
        if ($row['account_id']) {
            FamilyAccountActivityLog::query()->create([
                'family_account_id' => $row['account_id'],
                'actor_user_id' => auth()->id(),
                'action' => 'customer_onboarding_note',
                'metadata' => ['note' => trim($this->note)],
            ]);
        } else {
            Lead::query()->findOrFail($row['lead_id'])->activities()->create([
                'type' => 'note',
                'summary' => 'Customer onboarding note',
                'body' => trim($this->note),
                'actor_user_id' => auth()->id(),
                'occurred_at' => now(),
            ]);
        }
        $this->reset('note');
        session()->flash('onboarding_status', 'Note added.');
    }

    private function selectedAccount(): FamilyAccount
    {
        $row = app(CustomerOnboardingDirectory::class)->find($this->selectedKey);
        abort_unless($row['account_id'], 404);
        $account = FamilyAccount::query()->with('owner')->findOrFail($row['account_id']);
        abort_unless($account->status === FamilyAccount::STATUS_ACTIVE, 403);

        return $account;
    }

    public function render()
    {
        $directory = app(CustomerOnboardingDirectory::class);
        $selected = $this->selectedKey !== '' ? $directory->find($this->selectedKey) : null;
        $account = $selected && $selected['account_id']
            ? FamilyAccount::query()->with(['owner', 'onboarding.welcomeVisit'])->findOrFail($selected['account_id'])
            : null;
        $payments = app(FamilyPaymentSetupService::class);
        $payment = $account ? $payments->summaryForAccount($account) : null;
        $link = $payment['link'] ?? null;
        $linkUrl = $link && $link->status() === 'active' ? $payments->urlFor($link) : null;
        $lead = $selected && $selected['lead_id'] ? Lead::query()->find($selected['lead_id']) : null;
        $activity = collect();
        if ($account) {
            $activity = $account->activityLogs()->with('actor')
                ->where('action', '!=', 'customer_onboarding_account_prepared')
                ->latest('id')->limit(20)->get()->map(fn ($log) => [
                    'title' => match ($log->action) {
                        'customer_onboarding_note' => 'Team note',
                        'customer_onboarding_account_created', 'admin_customer_onboarding', 'account_created' => 'Family account created',
                        'customer_onboarding_account_prepared' => 'Family account prepared',
                        'customer_onboarding_access_email_sent' => 'Account access email sent',
                        'payment_setup_link_generated' => 'Payment setup link generated',
                        'payment_setup_link_revoked' => 'Payment setup link revoked',
                        'payment_method_saved' => 'Payment method saved',
                        default => ucfirst(str_replace('_', ' ', $log->action)),
                    },
                    'body' => data_get($log->metadata, 'note'),
                    'actor' => $log->actor?->name,
                    'at' => $log->created_at,
                ]);
        }
        if ($lead) {
            $leadActivity = $lead->activities()->with('actor')->limit(20)->get()
                ->reject(fn ($log) => $account && (
                    str_starts_with((string) data_get($log->metadata, 'event_key'), 'family_signup:')
                    || ($log->summary === 'Family account linked' && data_get($log->metadata, 'source') === 'customer_onboarding')
                ));
            $activity = $activity->concat($leadActivity->map(fn ($log) => [
                'title' => $log->summary,
                'body' => $log->type === 'note' && $log->actor_user_id ? $log->body : null,
                'actor' => $log->actor?->name,
                'at' => $log->occurred_at,
            ]));
        }

        return view('livewire.admin.customer-onboarding', [
            'families' => $selected ? null : $directory->paginate($this->search, $this->filter),
            'summary' => $selected ? null : $directory->summary(),
            'selected' => $selected,
            'account' => $account,
            'payment' => $payment,
            'paymentLink' => $link,
            'paymentLinkUrl' => $linkUrl,
            'lead' => $lead,
            'activity' => $activity->sortByDesc('at')->take(15),
        ])->title('Customer onboarding · LoLo Care');
    }
}
