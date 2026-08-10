<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamilyAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareRecipientProfile extends Model
{
    use BelongsToFamilyAccount, HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_ARCHIVED = 'archived';

    public const AGE_RANGES = [
        'under_18' => 'Under 18',
        '18_49' => '18-49',
        '50_64' => '50-64',
        '65_74' => '65-74',
        '75_84' => '75-84',
        '85_plus' => '85+',
        'prefer_not_to_say' => 'Prefer not to say',
    ];

    public const COMMUNICATION_OPTIONS = [
        'independent' => 'Converses independently',
        'extra_time' => 'Give extra time to answer',
        'short_clear_sentences' => 'Use short, clear sentences',
        'slow_face_person' => 'Speak slowly or face the person when speaking',
        'hearing_support' => 'Hearing support',
        'vision_support' => 'Vision support',
        'gestures' => 'Mostly non-speaking or uses gestures',
        'communication_device' => 'Communication device or picture board',
        'language_support' => 'Interpreter or preferred language support',
        'family_helps' => 'Family usually helps communicate',
    ];

    public const SUPPORT_AREAS = [
        'companionship' => 'Companionship and conversation',
        'meals' => 'Meal preparation or eating support',
        'mobility' => 'Walking or mobility support',
        'transfers' => 'Transfer support',
        'bathing_grooming' => 'Bathing or grooming support',
        'dressing' => 'Dressing support',
        'toileting' => 'Toileting or continence support',
        'memory_prompts' => 'Memory prompts or orientation',
        'medication_reminders' => 'Medication reminders only',
        'transportation' => 'Transportation or errands',
        'household_help' => 'Light household help',
        'overnight' => 'Overnight reassurance or supervision',
    ];

    public const MOBILITY_LEVELS = [
        'independent' => 'Independent',
        'uses_aid' => 'Uses an aid',
        'someone_nearby' => 'Needs someone nearby',
        'hands_on' => 'Needs hands-on help',
        'transfer_help' => 'Needs transfer help',
        'two_people_or_equipment' => 'Needs two people or specialized equipment',
        'not_sure' => 'Not sure',
    ];

    public const COMFORT_NEEDS = [
        'none_shared' => 'No special support shared',
        'reminders_reassurance' => 'Benefits from reminders or reassurance',
        'time_place_confusion' => 'May become confused about time or place',
        'repetition' => 'May repeat questions or activities',
        'unfamiliar_people_anxiety' => 'May feel anxious with unfamiliar people',
        'wandering' => 'May try to leave or wander',
        'care_distress' => 'May resist or become distressed during some care',
        'family_context' => 'Family would like to explain this in their own words',
    ];

    public const SAFETY_ITEMS = [
        'fall_risk' => 'Fall risk',
        'wandering' => 'Wandering or leaving unexpectedly',
        'swallowing' => 'Swallowing or choking concern',
        'seizure_history' => 'Seizure history shared by family',
        'allergy' => 'Allergy important for care',
        'skin_positioning' => 'Skin or positioning consideration',
        'oxygen_device' => 'Uses oxygen or another device a caregiver should be comfortable around',
        'two_person_transfer' => 'Two-person transfer or specialized equipment',
        'other' => 'Other important safety information',
    ];

    public const CAREGIVER_QUALITIES = [
        'patient' => 'Patient',
        'calm' => 'Calm',
        'conversational' => 'Conversational',
        'quiet' => 'Quiet',
        'encouraging' => 'Encouraging',
        'memory_support' => 'Experienced with memory support',
        'personal_care' => 'Comfortable with hands-on personal care',
        'overnight' => 'Comfortable overnight',
        'other' => 'Other',
    ];

    protected string $familyAccountOwnerColumnName = 'legacy_family_user_id';

    protected $fillable = [
        'family_account_id', 'legacy_family_user_id', 'legacy_family_recipient_profile_id',
        'created_by_user_id', 'updated_by_user_id', 'status', 'recipient_is_requester',
        'full_name', 'preferred_name', 'date_of_birth', 'age_range', 'pronouns',
        'relationship_to_family', 'about_them', 'interests_and_comforts', 'good_visit_notes',
        'communication_preferences', 'communication_notes', 'everyday_health_context',
        'support_areas', 'support_details', 'mobility_level', 'mobility_notes', 'routine_notes',
        'food_and_drink_notes', 'personal_care_preferences', 'sleep_overnight_notes',
        'comfort_needs', 'distress_triggers', 'calming_approaches', 'safety_items', 'safety_notes',
        'caregiver_quality_preferences', 'caregiver_do_notes', 'caregiver_avoid_notes',
        'include_additional_contact', 'additional_contact_name', 'additional_contact_relationship',
        'additional_contact_phone', 'additional_contact_email', 'assigned_escalation_notes',
        'sharing_acknowledged_at', 'sharing_acknowledged_by_user_id', 'last_reviewed_at',
        'latest_ready_version_id', 'revision', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_is_requester' => 'boolean',
            'date_of_birth' => 'date',
            'communication_preferences' => 'array',
            'support_areas' => 'array',
            'support_details' => 'array',
            'comfort_needs' => 'array',
            'safety_items' => 'array',
            'caregiver_quality_preferences' => 'array',
            'include_additional_contact' => 'boolean',
            'sharing_acknowledged_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
            'revision' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function legacyFamilyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'legacy_family_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CareRecipientProfileVersion::class);
    }

    public function latestReadyVersion(): BelongsTo
    {
        return $this->belongsTo(CareRecipientProfileVersion::class, 'latest_ready_version_id');
    }

    public function requestRecipients(): HasMany
    {
        return $this->hasMany(CareRecipient::class);
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }

    public function continuousCoveragePlans(): HasMany
    {
        return $this->hasMany(ContinuousCoveragePlan::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->latest_ready_version_id !== null;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function displayName(): string
    {
        return trim((string) $this->preferred_name)
            ?: trim((string) $this->full_name)
            ?: 'Care recipient';
    }

    public function hasMeaningfulShareableContent(): bool
    {
        foreach ([
            'about_them', 'interests_and_comforts', 'good_visit_notes', 'communication_notes',
            'everyday_health_context', 'mobility_notes', 'routine_notes', 'food_and_drink_notes',
            'personal_care_preferences', 'sleep_overnight_notes', 'distress_triggers',
            'calming_approaches', 'safety_notes', 'caregiver_do_notes', 'caregiver_avoid_notes',
        ] as $field) {
            if (trim((string) $this->{$field}) !== '') {
                return true;
            }
        }

        return collect([
            $this->communication_preferences,
            $this->support_areas,
            $this->comfort_needs,
            $this->safety_items,
            $this->caregiver_quality_preferences,
        ])->contains(fn ($value) => is_array($value) && $value !== []);
    }
}
