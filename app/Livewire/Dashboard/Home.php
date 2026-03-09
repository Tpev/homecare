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
                ->where('user_id', $user->id)
                ->first();

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
