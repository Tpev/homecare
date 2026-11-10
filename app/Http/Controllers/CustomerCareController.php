<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\ManualCareHourCreditService;
use App\Services\CustomerCare\ManagedCareService;
use App\Services\CustomerOnboarding\CustomerOnboardingAccountService;
use App\Services\Marketplace\CareRequestRecruitmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerCareController extends Controller
{
    public function index(Request $http): View
    {
        $archived = $http->query('view') === 'archived';
        $search = trim((string) $http->query('search', ''));
        $term = '%'.addcslashes(mb_substr($search, 0, 120), '%_\\').'%';
        $customers = User::where('role', 'family')->whereNull('access_revoked_at')
            ->when($search !== '', fn ($q) => $q->where(fn ($match) => $match->where('name', 'like', $term)->orWhere('email', 'like', $term)))
            ->orderBy('name')->paginate(15, ['*'], 'customers')->withQueryString();
        $requests = CareRequest::with(['family', 'booking'])->where('staff_managed', true)->where('is_system_generated', false)
            ->when($search !== '', fn ($q) => $q->where(fn ($match) => $match->where('title', 'like', $term)
                ->orWhereHas('family', fn ($family) => $family->where('name', 'like', $term)->orWhere('email', 'like', $term))))
            ->when($archived, fn ($q) => $q->whereNotNull('staff_archived_at'), fn ($q) => $q->whereNull('staff_archived_at'))
            ->withCount(['applications' => fn ($q) => $q->whereIn('status', ['applied', 'shortlisted'])])
            ->latest('updated_at')->paginate(20, ['*'], 'requests')->withQueryString();
        $attention = CareBooking::whereHas('careRequest', fn ($q) => $q->where('staff_managed', true)
            ->orWhereHas('carePlan.sourceCareRequest', fn ($source) => $source->where('staff_managed', true)))
            ->where(fn ($q) => $q->where(fn ($completed) => $completed->where('status', 'completed')->whereNull('family_confirmed_at'))
                ->orWhere('dispute_status', 'open')->orWhereHas('careHourFunding', fn ($funding) => $funding->where('status', 'payment_attention'))
                ->orWhereHas('careHourSettlement', fn ($settlement) => $settlement->where('card_minutes', '>', 0)->orWhereIn('status', ['payment_attention', 'funding_on_hold', 'reconciliation_pending']))
                ->orWhereHas('changeRequests', fn ($change) => $change->where('status', 'pending'))
                ->orWhereHas('timeCorrections', fn ($correction) => $correction->whereIn('status', ['pending_family', 'approved_admin_required', 'payment_action_required', 'escalated'])))
            ->with(['careRequest.family', 'careHourFunding', 'careHourSettlement'])->withCount(['changeRequests' => fn ($q) => $q->where('status', 'pending'),
                'timeCorrections' => fn ($q) => $q->whereIn('status', ['pending_family', 'approved_admin_required', 'payment_action_required', 'escalated'])])->orderBy('scheduled_start_at')->limit(30)->get();

        $inbox = \App\Models\CareRequestConversation::whereHas('careRequest', fn ($q) => $q->where('staff_managed', true)
            ->orWhereHas('carePlan.sourceCareRequest', fn ($source) => $source->where('staff_managed', true)))
            ->whereColumn('last_message_sender_id', 'caregiver_user_id')
            ->where(fn ($q) => $q->whereNull('staff_last_read_at')->orWhereColumn('last_message_at', '>', 'staff_last_read_at'))
            ->with(['caregiver', 'careRequest.family'])->latest('last_message_at')->limit(30)->get();

        return view('customer-care.index', compact('search', 'customers', 'requests', 'attention', 'inbox', 'archived'));
    }

    public function gifts(Request $http): View
    {
        $search = trim((string) $http->query('search', ''));
        $gifts = \App\Models\CareHourPurchase::whereHas('gift')->with('gift')
            ->when($search !== '', fn ($q) => $q->where(fn ($match) => $match->where('buyer_email', $search)
                ->orWhere('public_id', $search)->orWhere('buyer_name', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->latest()->paginate(25)->withQueryString();

        return view('customer-care.gifts', compact('gifts', 'search'));
    }

    public function createCustomer(Request $http, CustomerOnboardingAccountService $accounts): RedirectResponse
    {
        $account = $accounts->create($http->user(), $http->only('name', 'email', 'phone'));

        return redirect()->route('customer-care.customer', $account->owner_user_id)->with('status', 'Customer account prepared. No access email was sent.');
    }

    public function customer(Request $http, User $customer): View
    {
        abort_unless($customer->role === 'family' && $customer->access_revoked_at === null, 404);
        $wallet = app(CareHourIdentityService::class)->walletFor($customer);
        $family = app(\App\Services\FamilyAccounts\FamilyAccountContext::class)->account($customer);
        $archived = $http->query('view') === 'archived';
        $requests = CareRequest::where('family_account_id', $family->id)->where('is_system_generated', false)
            ->when($archived, fn ($q) => $q->whereNotNull('staff_archived_at'), fn ($q) => $q->whereNull('staff_archived_at'))
            ->latest()->paginate(20)->withQueryString();
        $credits = DB::table('care_hour_manual_credits')->join('users', 'users.id', '=', 'care_hour_manual_credits.actor_user_id')
            ->where('family_account_id', $family->id)->select('care_hour_manual_credits.*', 'users.name as actor_name')->latest('care_hour_manual_credits.id')->limit(30)->get();

        return view('customer-care.customer', compact('customer', 'wallet', 'requests', 'credits', 'archived'));
    }

    public function credit(Request $http, User $customer, ManualCareHourCreditService $credits): RedirectResponse
    {
        $credits->credit($http->user(), $customer, $http->only('request_key', 'hours', 'amount', 'reference', 'reason'));

        return redirect()->route('customer-care.customer', $customer)->with('status', 'Prepaid hours recorded. The same payment reference cannot be credited twice.');
    }

    public function create(User $customer): View
    {
        abort_unless($customer->role === 'family' && $customer->access_revoked_at === null, 404);

        return view('customer-care.edit', ['customer' => $customer, 'careRequest' => null, 'tasks' => CareTask::orderBy('name')->get()]);
    }

    public function edit(CareRequest $careRequest): View
    {
        abort_unless($careRequest->staff_managed, 403);
        abort_unless(! $careRequest->staff_archived_at && in_array($careRequest->status, ['draft', 'open'], true) && ! $careRequest->booking()->exists(), 409);

        return view('customer-care.edit', ['customer' => $careRequest->family, 'careRequest' => $careRequest->load('tasks', 'recipient'), 'tasks' => CareTask::orderBy('name')->get()]);
    }

    public function store(Request $http, User $customer, ManagedCareService $care): RedirectResponse
    {
        $careRequest = $care->save($http->user(), $customer, $http->all());

        return redirect()->route('customer-care.show', $careRequest)->with('status', 'Request saved. Customer notifications are off.');
    }

    public function update(Request $http, CareRequest $careRequest, ManagedCareService $care): RedirectResponse
    {
        $care->save($http->user(), $careRequest->family, $http->all(), $careRequest);

        return redirect()->route('customer-care.show', $careRequest)->with('status', 'Request updated.');
    }

    public function show(Request $http, CareRequest $careRequest, CareRequestRecruitmentService $recruitment): View|RedirectResponse
    {
        $tab = in_array($http->query('panel', $http->query('tab')), ['visits', 'messages', 'activity'], true) ? $http->query('panel', $http->query('tab')) : 'overview';
        $careRequest->load(['family', 'recipient', 'tasks', 'applications.caregiver', 'carePlan']);
        $bookings = CareBooking::where(fn ($q) => $q->where('care_request_id', $careRequest->id)
            ->orWhereHas('carePlan', fn ($plan) => $plan->where('source_care_request_id', $careRequest->id)))
            ->with(['caregiver', 'careHourFunding', 'careHourSettlement', 'timeCorrections', 'changeRequests'])->orderBy('scheduled_start_at')->paginate(20)->withQueryString();
        $conversations = \App\Models\CareRequestConversation::whereHas('careRequest', fn ($q) => $q->whereKey($careRequest->id)
            ->orWhereHas('carePlan', fn ($plan) => $plan->where('source_care_request_id', $careRequest->id)))
            ->with(['caregiver', 'messages.sender'])->orderByDesc('last_message_at')->get();
        $unreadMessages = $conversations->filter(fn ($thread) => $thread->last_message_sender_id === $thread->caregiver_user_id
            && $thread->last_message_at && (! $thread->staff_last_read_at || $thread->last_message_at->gt($thread->staff_last_read_at)))->count();
        if ($careRequest->staff_managed && $tab === 'messages') {
            foreach ($conversations as $conversation) {
                if ($readAt = $conversation->messages->max('created_at')) {
                    $conversation->update(['staff_last_read_at' => $readAt]);
                }
            }
        }
        $history = DB::table('customer_care_actions')->join('users', 'users.id', '=', 'customer_care_actions.actor_user_id')
            ->where('care_request_id', $careRequest->id)->select('customer_care_actions.*', 'users.name as actor_name')->latest('customer_care_actions.id')->limit(60)->get();
        $caregiverSearch = trim((string) $http->query('caregiver_search', ''));
        $candidates = $recruitment->candidates($careRequest, $caregiverSearch)->limit(30)->get();
        $placements = $recruitment->candidates($careRequest, $caregiverSearch, includeContacted: true)->limit(30)->get();
        $wallet = app(CareHourIdentityService::class)->walletForFamily((int) $careRequest->family_account_id);

        return view('customer-care.show', compact('careRequest', 'bookings', 'history', 'candidates', 'caregiverSearch', 'wallet', 'conversations', 'placements', 'tab', 'unreadMessages'));
    }

    public function action(Request $http, CareRequest $careRequest, ManagedCareService $care, CareRequestRecruitmentService $recruitment): RedirectResponse
    {
        $action = (string) $http->input('action');
        $reason = trim((string) $http->input('reason', ''));
        $actor = $http->user();
        try {
            if ($action === 'take_over') {
                $care->takeOver($actor, $careRequest, $reason);
            } else {
                abort_unless($careRequest->staff_managed, 403);
                abort_if($careRequest->staff_archived_at && ! in_array($action, ['restore', 'archive'], true), 409);
                match ($action) {
                    'archive' => $care->archive($actor, $careRequest, $reason),
                    'restore' => $care->restore($actor, $careRequest),
                    'hire' => $care->hire($actor, $careRequest, (int) $http->input('caregiver_id'), $reason),
                    'shortlist', 'reject' => $care->application($actor, $careRequest, (int) $http->input('application_id'), $action === 'shortlist' ? 'shortlisted' : 'rejected'),
                    'withdraw' => $care->withdraw($actor, $careRequest, $reason),
                    'note' => $care->note($actor, $careRequest, $reason),
                    'message' => $care->message($actor, $careRequest, (int) $http->input('caregiver_id'), (string) $http->input('body'), $http->filled('conversation_id') ? (int) $http->input('conversation_id') : null),
                    'recruit' => $recruitment->enqueue($actor, $careRequest, (array) $http->input('caregiver_ids', [])),
                    'visit' => $care->visit($actor, $careRequest, (int) $http->input('booking_id'), (string) $http->input('visit_action'), $http->all()),
                    'plan' => $care->plan($actor, $careRequest, (string) $http->input('plan_action'), $reason, $http->all()),
                    'change' => $care->change($actor, $careRequest, (int) $http->input('booking_id'), (int) $http->input('change_id'), (string) $http->input('decision'), $reason),
                    'correction' => $care->correction($actor, $careRequest, (int) $http->input('booking_id'), (int) $http->input('correction_id'), (string) $http->input('decision'), $reason),
                    default => abort(422),
                };
            }
        } catch (PaymentException $exception) {
            return back()->withErrors(['payment' => $exception->userMessage]);
        }

        if ($action === 'archive') {
            return redirect()->route('customer-care.customer', $careRequest->family_user_id)->with('status', 'Request removed from the active list. Its history is available in Archived requests.');
        }
        $tab = match ($action) {
            'hire', 'visit', 'plan', 'change', 'correction' => 'visits',
            'recruit', 'shortlist', 'reject' => 'caregivers',
            'message' => 'messages',
            'note' => 'activity',
            default => 'overview',
        };

        return redirect()->route('customer-care.show', ['careRequest' => $careRequest, 'tab' => $tab])
            ->with('status', $action === 'restore' ? 'Request restored to the active list. Its posting remains closed.' : 'Saved by the care team. No customer notification was sent.');
    }
}
