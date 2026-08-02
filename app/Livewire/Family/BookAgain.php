<?php

namespace App\Livewire\Family;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\CaregiverPrelaunch;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use App\Support\MarketplacePricing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BookAgain extends Component
{
    public CareRequest $sourceRequest;

    public string $visitDate = '';

    public string $startTime = '';

    public string $durationMinutes = '120';

    public string $message = '';

    /**
     * @var list<array{label:string,value:string}>
     */
    public array $durationOptions = [];

    public function mount(int $careRequest): void
    {
        $this->sourceRequest = CareRequest::query()
            ->with([
                'family',
                'recipient',
                'thirdPartyContact',
                'tasks',
                'booking',
                'applications.caregiver.caregiverProfile',
            ])
            ->findOrFail($careRequest);

        abort_unless((int) $this->sourceRequest->family_user_id === (int) auth()->id(), 403);
        abort_unless($this->sourceIsEligible(), 404);

        $hiredApplication = $this->hiredApplication();
        $caregiverName = trim((string) ($hiredApplication?->caregiver?->name ?? 'your caregiver'));

        if ($hiredApplication?->caregiver && ! CaregiverPrelaunch::familyCanProceedWithCaregiver(
            $hiredApplication->caregiver->email,
            $this->sourceRequest,
            (int) $hiredApplication->caregiver_user_id,
        )) {
            abort(404);
        }

        $this->durationOptions = $this->buildDurationOptions();

        $start = $this->defaultNextStart();
        $this->visitDate = $start->toDateString();
        $this->startTime = $start->format('H:i');
        $this->durationMinutes = (string) $this->defaultDurationMinutes();
        $this->message = 'Hi '.$this->firstName($caregiverName).', we would like to book you again for one more visit.';
    }

    public function sendOneTimeInvite(): void
    {
        $this->validate([
            'visitDate' => ['required', 'date', 'after_or_equal:today'],
            'startTime' => ['required', 'date_format:H:i'],
            'durationMinutes' => ['required', 'integer', Rule::in(array_column($this->durationOptions, 'value'))],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $start = $this->parseStart();
        if (! $start || $start->lte(now())) {
            throw ValidationException::withMessages([
                'visitDate' => 'Choose a future date and time.',
            ]);
        }

        $duration = (int) $this->durationMinutes;
        $end = $start->copy()->addMinutes($duration);
        $source = $this->sourceRequest->fresh([
            'family',
            'recipient',
            'thirdPartyContact',
            'tasks',
            'booking',
            'applications.caregiver.caregiverProfile',
        ]);

        if (! $source) {
            abort(404);
        }

        $this->sourceRequest = $source;
        abort_unless($this->sourceIsEligible(), 404);

        $hiredApplication = $this->hiredApplication();
        if (! $hiredApplication?->caregiver) {
            abort(404);
        }

        if (! CaregiverPrelaunch::familyCanProceedWithCaregiver(
            $hiredApplication->caregiver->email,
            $this->sourceRequest,
            (int) $hiredApplication->caregiver_user_id,
        )) {
            session()->flash('status', CaregiverPrelaunch::familyHireMessage());

            return;
        }

        $newRequest = DB::transaction(function () use ($hiredApplication, $start, $end): CareRequest {
            $source = $this->sourceRequest;
            $recipientName = trim((string) ($source->recipient?->full_name ?? ''));
            $recipientName = $recipientName !== '' ? $recipientName : 'the care recipient';
            $hourlyRate = app(MarketplacePricing::class)->hourlyRateForFamily(
                auth()->user(),
                (float) config('marketplace.family_estimate_hourly_rate', 30.00)
            );

            $newRequest = CareRequest::query()->create([
                'family_user_id' => auth()->id(),
                'title' => 'One-time care for '.$recipientName,
                'additional_info' => $source->additional_info,
                'scope_of_work' => $source->scope_of_work,
                'time_expectations' => 'One visit on '.$start->format('M d, Y').' from '.$start->format('g:i A').' to '.$end->format('g:i A').'.',
                'home_access_notes' => $source->home_access_notes,
                'preferred_response_hours' => $source->preferred_response_hours ?: CareRequestInvitation::SLA_HOURS,
                'status' => CareRequest::STATUS_OPEN,
                'request_type' => CareRequest::TYPE_ONE_TIME,
                'budget_min' => $hourlyRate,
                'budget_max' => $hourlyRate,
                'requested_start_at' => $start,
                'requested_end_at' => $end,
                'address_line1' => $source->address_line1,
                'address_line2' => $source->address_line2,
                'city' => $source->city,
                'state' => $source->state,
                'zip' => $source->zip,
                'lat' => $source->lat,
                'lng' => $source->lng,
            ]);

            if ($source->recipient) {
                $newRequest->recipient()->create($source->recipient->only([
                    'recipient_is_requester',
                    'full_name',
                    'date_of_birth',
                    'gender',
                    'mobility_level',
                    'relationship_to_family',
                    'care_notes',
                ]));
            }

            if ($source->thirdPartyContact) {
                $newRequest->thirdPartyContact()->create($source->thirdPartyContact->only([
                    'full_name',
                    'relationship_to_recipient',
                    'phone',
                    'email',
                ]));
            }

            $taskPayload = $source->tasks
                ->mapWithKeys(fn ($task) => [$task->id => ['task_note' => $task->pivot?->task_note]])
                ->all();
            $newRequest->tasks()->sync($taskPayload);

            CareRequestInvitation::query()->create([
                'care_request_id' => $newRequest->id,
                'family_user_id' => auth()->id(),
                'caregiver_user_id' => $hiredApplication->caregiver_user_id,
                'status' => CareRequestInvitation::STATUS_PENDING,
                'message' => trim($this->message) ?: 'We would like to book you again for one more visit.',
                'expires_at' => now()->addHours(72),
            ]);

            return $newRequest;
        });

        $invitation = $newRequest->invitations()->latest('id')->first();
        $caregiver = $hiredApplication->caregiver;

        app(MarketplaceNotificationService::class)->notify(
            recipients: $caregiver,
            eventKey: MarketplaceEvent::INVITATION_RECEIVED,
            title: auth()->user()->name.' wants to book you again',
            body: 'Review the new date, time, location, and care details before you respond.',
            url: route('caregiver.invitations.index'),
            payload: ['care_request_id' => $newRequest->id],
            subject: $invitation,
            dedupeKey: 'book-again-invite:request-'.$newRequest->id.'-caregiver-'.$caregiver->id
        );

        FunnelTracker::track('care_request_book_again_invite_sent', auth()->user(), $newRequest, [
            'source_request_id' => $this->sourceRequest->id,
            'caregiver_user_id' => $caregiver->id,
        ]);

        session()->flash('status', 'One-time invite sent to '.$caregiver->name.'.');
        $this->redirect(route('family.requests.show', $newRequest->id, false), navigate: true);
    }

    public function render()
    {
        $hiredApplication = $this->hiredApplication();

        return view('livewire.family.book-again', [
            'hiredApplication' => $hiredApplication,
            'caregiverName' => trim((string) ($hiredApplication?->caregiver?->name ?? 'Your caregiver')),
            'caregiverFirstName' => $this->firstName((string) ($hiredApplication?->caregiver?->name ?? 'caregiver')),
        ]);
    }

    private function sourceIsEligible(): bool
    {
        if ($this->sourceRequest->care_plan_id) {
            return false;
        }

        $booking = $this->sourceRequest->booking;
        $application = $this->hiredApplication();

        return $booking
            && $application
            && in_array((string) $booking->status, [
                CareBooking::STATUS_COMPLETED,
                CareBooking::STATUS_REVIEWED,
            ], true)
            && ($booking->family_confirmed_at || $booking->status === CareBooking::STATUS_REVIEWED);
    }

    private function hiredApplication(): ?CareRequestApplication
    {
        $booking = $this->sourceRequest->booking;
        if ($booking?->care_request_application_id) {
            return $this->sourceRequest->applications->firstWhere('id', $booking->care_request_application_id);
        }

        return $this->sourceRequest->applications->firstWhere('status', CareRequestApplication::STATUS_HIRED);
    }

    private function defaultNextStart(): Carbon
    {
        $reference = $this->sourceRequest->booking?->scheduled_start_at
            ?: $this->sourceRequest->requested_start_at
            ?: now()->addDay()->setTime(9, 0);

        $start = Carbon::parse($reference)->copy()->addWeek();
        while ($start->lte(now())) {
            $start->addWeek();
        }

        return $start;
    }

    private function defaultDurationMinutes(): int
    {
        $booking = $this->sourceRequest->booking;
        $minutes = null;

        if ($booking?->scheduled_start_at && $booking?->scheduled_end_at) {
            $minutes = (int) $booking->scheduled_start_at->diffInMinutes($booking->scheduled_end_at, false);
        }

        if (($minutes ?? 0) <= 0 && (int) ($booking?->worked_minutes ?? 0) > 0) {
            $minutes = (int) $booking->worked_minutes;
        }

        $minutes = max(60, min(720, (int) ($minutes ?: 120)));

        return (int) (round($minutes / 30) * 30);
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function buildDurationOptions(): array
    {
        return collect(range(60, 720, 30))
            ->map(fn (int $minutes): array => [
                'value' => (string) $minutes,
                'label' => intdiv($minutes, 60).'h'.($minutes % 60 ? ' '.($minutes % 60).'m' : ''),
            ])
            ->all();
    }

    private function parseStart(): ?Carbon
    {
        try {
            return Carbon::createFromFormat(
                'Y-m-d H:i',
                trim($this->visitDate).' '.trim($this->startTime),
                config('app.timezone')
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function firstName(string $name): string
    {
        $name = trim($name);

        return $name !== '' ? (string) str($name)->before(' ') : 'caregiver';
    }
}
