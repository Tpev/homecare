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
        'stripe_customer_id',
        'password',
        'role',
        'phone',
        'city',
        'state',
        'date_of_birth',
        'onboarding_completed_at',
        'family_reliability_score',
        'family_completed_bookings_count',
        'family_cancellation_count',
        'family_dispute_count',
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
            'family_reliability_score' => 'decimal:2',
            'family_completed_bookings_count' => 'integer',
            'family_cancellation_count' => 'integer',
            'family_dispute_count' => 'integer',
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

    public function aiRequestSessions(): HasMany
    {
        return $this->hasMany(AiRequestSession::class, 'family_user_id');
    }

    public function caregiverIdentityVerifications(): HasMany
    {
        return $this->hasMany(CaregiverIdentityVerification::class);
    }

    public function caregiverPayouts(): HasMany
    {
        return $this->hasMany(CaregiverPayout::class, 'caregiver_user_id');
    }

    public function caregiverPayoutItems(): HasMany
    {
        return $this->hasMany(CaregiverPayoutItem::class, 'caregiver_user_id');
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(MarketplaceNotificationDelivery::class);
    }

    public function familyBookingPayments(): HasMany
    {
        return $this->hasMany(CareBookingPayment::class, 'family_user_id');
    }

    public function caregiverBookingPayments(): HasMany
    {
        return $this->hasMany(CareBookingPayment::class, 'caregiver_user_id');
    }

    public function familyHouseholdProfile(): HasOne
    {
        return $this->hasOne(FamilyHouseholdProfile::class, 'family_user_id');
    }

    public function familyRecipientProfile(): HasOne
    {
        return $this->hasOne(FamilyRecipientProfile::class, 'family_user_id');
    }
}
