<?php

namespace App\Livewire\Dashboard;

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

            $familyData['stats'] = [
                'open_requests' => (clone $requestQuery)->where('status', CareRequest::STATUS_OPEN)->count(),
                'filled_requests' => (clone $requestQuery)->where('status', CareRequest::STATUS_FILLED)->count(),
                'total_applicants' => CareRequestApplication::query()
                    ->whereHas('careRequest', fn ($q) => $q->where('family_user_id', $user->id))
                    ->count(),
                'unread_messages' => $this->unreadMessagesCount($user->id, 'family'),
            ];

            $familyData['upcoming_requests'] = CareRequest::query()
                ->with(['recipient'])
                ->withCount(['applications'])
                ->where('family_user_id', $user->id)
                ->whereIn('status', [CareRequest::STATUS_OPEN, CareRequest::STATUS_FILLED])
                ->orderBy('requested_start_at')
                ->limit(6)
                ->get();

            $familyData['recent_applicants'] = CareRequestApplication::query()
                ->with([
                    'careRequest:id,title,family_user_id,status',
                    'caregiver:id,name',
                    'conversation:id,care_request_application_id',
                ])
                ->whereHas('careRequest', fn ($q) => $q->where('family_user_id', $user->id))
                ->latest()
                ->limit(8)
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
