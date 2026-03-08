<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'city',
        'state',
        'date_of_birth',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function caregiverProfile(): HasOne
    {
        return $this->hasOne(CaregiverProfile::class);
    }

    public function careRequests(): HasMany
    {
        return $this->hasMany(CareRequest::class, 'family_user_id');
    }

    public function careRequestApplications(): HasMany
    {
        return $this->hasMany(CareRequestApplication::class, 'caregiver_user_id');
    }

    public function familyConversations(): HasMany
    {
        return $this->hasMany(CareRequestConversation::class, 'family_user_id');
    }

    public function caregiverConversations(): HasMany
    {
        return $this->hasMany(CareRequestConversation::class, 'caregiver_user_id');
    }

    public function sentCareRequestMessages(): HasMany
    {
        return $this->hasMany(CareRequestMessage::class, 'sender_user_id');
    }

    public function sentCareRequestInvitations(): HasMany
    {
        return $this->hasMany(CareRequestInvitation::class, 'family_user_id');
    }

    public function receivedCareRequestInvitations(): HasMany
    {
        return $this->hasMany(CareRequestInvitation::class, 'caregiver_user_id');
    }

    public function familyBookings(): HasMany
    {
        return $this->hasMany(CareBooking::class, 'family_user_id');
    }

    public function caregiverBookings(): HasMany
    {
        return $this->hasMany(CareBooking::class, 'caregiver_user_id');
    }

    public function givenCareReviews(): HasMany
    {
        return $this->hasMany(CareReview::class, 'reviewer_user_id');
    }

    public function receivedCareReviews(): HasMany
    {
        return $this->hasMany(CareReview::class, 'reviewee_user_id');
    }

    public function openedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'opener_user_id');
    }

    public function familyFavorites(): HasMany
    {
        return $this->hasMany(FamilyCaregiverFavorite::class, 'family_user_id');
    }

    public function favoritedByFamilies(): HasMany
    {
        return $this->hasMany(FamilyCaregiverFavorite::class, 'caregiver_user_id');
    }
}
