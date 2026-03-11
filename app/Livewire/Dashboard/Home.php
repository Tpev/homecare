<?php

namespace App\Livewire\Dashboard;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\CaregiverProfile;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Home extends Component
{
    public function render()
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $mode = $user->role;

        $familyData = [];
        $caregiverData = [];

        if ($mode === 'family') {
            $requestQuery = CareRequest::query()->where('family_user_id', $user->id);
            $openRequestQuery = CareRequest::query()
                ->where('family_user_id', $user->id)
                ->where('status', CareRequest::STATUS_OPEN);

            $readyToReviewCount = (clone $openRequestQuery)
                ->whereHas('applications', function ($query) {
                    $query->whereIn('status', [
                        CareRequestApplication::STATUS_APPLIED,
                        CareRequestApplication::STATUS_SHORTLISTED,
                    ]);
                })
                ->count();

            $waitingApplicantsCount = (clone $openRequestQuery)
                ->whereDoesntHave('applications')
                ->count();

            $activeShiftCount = CareRequest::query()
                ->where('family_user_id', $user->id)
                ->whereHas('booking', function ($query) {
                    $query->whereIn('status', [
                        CareBooking::STATUS_SCHEDULED,
                        CareBooking::STATUS_IN_PROGRESS,
                        CareBooking::STATUS_PAUSED,
                        CareBooking::STATUS_COMPLETED,
                    ]);
                })
                ->count();

            $familyData['stats'] = [
                'open_requests' => (clone $requestQuery)->where('status', CareRequest::STATUS_OPEN)->count(),
                'ready_to_review' => $readyToReviewCount,
                'waiting_for_applicants' => $waitingApplicantsCount,
                'active_shifts' => $activeShiftCount,
                'unread_messages' => $this->unreadMessagesCount($user->id, 'family'),
            ];

            $familyData['urgent_open_requests'] = (clone $openRequestQuery)
                ->whereDoesntHave('applications')
                ->where('created_at', '<=', now()->subHours(6))
                ->count();

            $familyData['focus_requests'] = CareRequest::query()
                ->with(['recipient', 'booking'])
                ->withCount([
                    'applications',
                    'applications as pending_candidate_count' => function ($query) {
                        $query->whereIn('status', [
                            CareRequestApplication::STATUS_APPLIED,
                            CareRequestApplication::STATUS_SHORTLISTED,
                        ]);
                    },
                ])
                ->where('family_user_id', $user->id)
                ->whereIn('status', [CareRequest::STATUS_OPEN, CareRequest::STATUS_FILLED])
                ->orderByRaw("CASE WHEN status = '".CareRequest::STATUS_OPEN."' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get();

            $familyData['active_shifts'] = CareRequest::query()
                ->with(['recipient', 'booking'])
                ->where('family_user_id', $user->id)
                ->whereHas('booking', function ($query) {
                    $query->whereIn('status', [
                        CareBooking::STATUS_SCHEDULED,
                        CareBooking::STATUS_IN_PROGRESS,
                        CareBooking::STATUS_PAUSED,
                        CareBooking::STATUS_COMPLETED,
                    ]);
                })
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get();

            $familyData['recent_applicants'] = CareRequestApplication::query()
                ->with([
                    'careRequest:id,title,family_user_id,status',
                    'caregiver:id,name',
                    'conversation:id,care_request_application_id',
                ])
                ->whereHas('careRequest', fn ($q) => $q->where('family_user_id', $user->id))
                ->latest()
                ->limit(6)
                ->get();
        }

        if ($mode === 'caregiver') {
            $caregiverData['profile'] = CaregiverProfile::query()
                ->firstOrCreate(
                    ['user_id' => $user->id],
                    ['status' => 'draft']
                );

            $caregiverData['stats'] = [
                'applications_total' => CareRequestApplication::query()->where('caregiver_user_id', $user->id)->count(),
                'shortlisted' => CareRequestApplication::query()
                    ->where('caregiver_user_id', $user->id)
                    ->where('status', CareRequestApplication::STATUS_SHORTLISTED)
                    ->count(),
                'hired' => CareRequestApplication::query()
                    ->where('caregiver_user_id', $user->id)
                    ->where('status', CareRequestApplication::STATUS_HIRED)
                    ->count(),
                'invitations_pending' => CareRequestInvitation::query()
                    ->where('caregiver_user_id', $user->id)
                    ->where('status', CareRequestInvitation::STATUS_PENDING)
                    ->where(function ($query) {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                    })
                    ->count(),
                'unread_messages' => $this->unreadMessagesCount($user->id, 'caregiver'),
            ];

            $caregiverData['recent_applications'] = CareRequestApplication::query()
                ->with(['careRequest:id,title,status,city,state,requested_start_at'])
                ->where('caregiver_user_id', $user->id)
                ->latest()
                ->limit(8)
                ->get();

            $caregiverData['recent_invitations'] = CareRequestInvitation::query()
                ->with(['careRequest:id,title,request_type,city,state,requested_start_at', 'family:id,name'])
                ->where('caregiver_user_id', $user->id)
                ->latest()
                ->limit(6)
                ->get();

            $caregiverData['active_shift'] = CareBooking::query()
                ->with(['careRequest:id,title,city,state,request_type,requested_start_at,requested_end_at'])
                ->where('caregiver_user_id', $user->id)
                ->whereIn('status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                ->latest('started_at')
                ->first();

            $caregiverData['next_shift'] = CareBooking::query()
                ->with(['careRequest:id,title,city,state,request_type,requested_start_at,requested_end_at'])
                ->where('caregiver_user_id', $user->id)
                ->where('status', CareBooking::STATUS_SCHEDULED)
                ->orderBy('scheduled_start_at')
                ->first();

            $caregiverData['quick_shifts'] = CareBooking::query()
                ->with(['careRequest:id,title,city,state,request_type,requested_start_at,requested_end_at'])
                ->where('caregiver_user_id', $user->id)
                ->whereIn('status', [
                    CareBooking::STATUS_IN_PROGRESS,
                    CareBooking::STATUS_PAUSED,
                    CareBooking::STATUS_SCHEDULED,
                    CareBooking::STATUS_COMPLETED,
                ])
                ->orderByRaw("CASE status
                    WHEN 'in_progress' THEN 0
                    WHEN 'paused' THEN 1
                    WHEN 'scheduled' THEN 2
                    WHEN 'completed' THEN 3
                    ELSE 4 END")
                ->orderBy('scheduled_start_at')
                ->limit(3)
                ->get();

            $profile = $caregiverData['profile'];
            $identityComplete = $profile->hasIdentityVerifiedBadge();
            $tasksComplete = $profile->skills()->exists();
            $insuranceComplete = $profile->insuranceIsComplete();
            $videoComplete = filled($profile->intro_video_path);

            $basicsComplete = filled($profile->bio)
                && ! is_null($profile->years_experience)
                && filled($profile->service_area_zip)
                && ! is_null($profile->service_radius_miles)
                && $profile->languages()->exists()
                && $profile->availabilities()->exists();

            $caregiverData['setup_cards'] = [
                [
                    'title' => 'Complete profile basics',
                    'description' => 'Bio, location, languages, and precise availability.',
                    'route' => route('caregiver.onboarding'),
                    'cta' => 'Open basics',
                    'required' => true,
                    'done' => $basicsComplete,
                ],
                [
                    'title' => 'Identity verification',
                    'description' => 'Required to be review-eligible and searchable.',
                    'route' => route('caregiver.verification.show'),
                    'cta' => 'Start KYC',
                    'required' => true,
                    'done' => $identityComplete,
                ],
                [
                    'title' => 'Task comfort selection',
                    'description' => 'Pick exactly which care tasks you are comfortable doing.',
                    'route' => route('caregiver.tasks.edit'),
                    'cta' => 'Select tasks',
                    'required' => true,
                    'done' => $tasksComplete,
                ],
                [
                    'title' => 'Insurance setup',
                    'description' => 'Tell families whether you are insured and upload proof if yes.',
                    'route' => route('caregiver.insurance.edit'),
                    'cta' => 'Set insurance',
                    'required' => false,
                    'done' => $insuranceComplete,
                ],
                [
                    'title' => 'Intro video',
                    'description' => 'Optional, but usually improves profile conversion.',
                    'route' => route('caregiver.video.edit'),
                    'cta' => 'Upload video',
                    'required' => false,
                    'done' => $videoComplete,
                ],
            ];

            $caregiverData['setup_cards'] = collect($caregiverData['setup_cards'])
                ->filter(fn ($card) => ! $card['done'])
                ->values()
                ->all();

            $requiredCards = collect($caregiverData['setup_cards'])->filter(fn ($card) => $card['required']);
            $requiredTotal = 3;
            $requiredCompleted = $requiredTotal - $requiredCards->count();

            $caregiverData['required_setup_total'] = $requiredTotal;
            $caregiverData['required_setup_completed'] = $requiredCompleted;
            $caregiverData['ready_for_review'] = $requiredCompleted >= $requiredTotal;
            $caregiverData['can_submit_for_review'] = $caregiverData['ready_for_review']
                && in_array((string) $profile->status, ['draft', 'suspended'], true);
        }

        return view('livewire.dashboard.home', compact('mode', 'familyData', 'caregiverData'));
    }

    private function unreadMessagesCount(int $userId, string $role): int
    {
        if (! Schema::hasTable('care_request_conversations')) {
            return 0;
        }

        if ($role === 'family') {
            return CareRequestConversation::query()
                ->where('family_user_id', $userId)
                ->whereNotNull('last_message_at')
                ->where('last_message_sender_id', '!=', $userId)
                ->where(function ($query) {
                    $query->whereNull('family_last_read_at')
                        ->orWhereColumn('last_message_at', '>', 'family_last_read_at');
                })
                ->count();
        }

        return CareRequestConversation::query()
            ->where('caregiver_user_id', $userId)
            ->whereNotNull('last_message_at')
            ->where('last_message_sender_id', '!=', $userId)
            ->where(function ($query) {
                $query->whereNull('caregiver_last_read_at')
                    ->orWhereColumn('last_message_at', '>', 'caregiver_last_read_at');
            })
            ->count();
    }
}
