<?php

namespace App\Services\Reporting;

/** Versioned business export contract. Never derive exports from model fillable or SELECT *. */
final class ReportingCatalog
{
    public const VERSION = 1;

    public static function datasets(): array
    {
        // Every dataset has a single durable id, except explicit pivot composite keys below.
        // id and retained created_at/updated_at are added consistently by definition().
        $rows = [
            'users' => ['name email phone role city state onboarding_completed_at access_revoked_at sms_opt_in sms_opted_in_at', '', 'id role access_revoked_at'],
            'leads' => ['family_account_id lead_type name email phone company location zip data status assigned_admin_id priority source source_detail external_source external_id contact_role submitted_at first_call_at first_connected_at call_attempt_count unanswered_attempt_count last_contacted_at next_follow_up_at converted_at do_not_contact_at closed_reason', 'data', 'id lead_type status family_account_id'],
            'lead_external_identities' => ['lead_id external_source external_id'],
            'lead_activities' => ['lead_id sms_message_id actor_user_id type summary body occurred_at metadata', 'metadata', 'id lead_id type body occurred_at'],
            'lead_welcome_emails' => ['lead_id email status reason subject body attempts last_attempt_at sent_at previewed_at unsubscribed_at account_user_id account_created_at account_linked_at care_request_id request_posted_at'],
            'family_accounts' => ['owner_user_id status default_care_recipient_profile_id payment_method_status payment_method_checked_at payment_method_refresh_attempted_at payment_method_snapshot_version', '', 'id owner_user_id'],
            'family_payment_setup_links' => ['family_account_id created_by_user_id expires_at revoked_at completed_at consented_at consent_version checkout_attempt'],
            'family_recipient_profiles' => ['family_account_id family_user_id recipient_is_requester full_name mobility_level relationship_to_family care_notes'],
            'family_household_profiles' => ['family_account_id family_user_id city state zip time_expectations preferred_response_hours'],
            'family_account_members' => ['family_account_id user_id access_level status joined_at ended_at ended_by_user_id'],
            'family_account_activity_logs' => ['family_account_id actor_user_id action subject_user_id metadata', 'metadata'],
            'family_onboardings' => ['family_account_id initiated_by_user_id completed_by_user_id care_recipient_profile_id source status exemption_reason schema_version current_step revision draft request_context submitted_snapshot started_at completed_at request_handoff_completed_at', 'draft request_context submitted_snapshot'],
            'family_welcome_visits' => ['family_onboarding_id preferred_date preferred_range timezone window_start_at window_end_at phone status confirmed_start_at staff_note handled_by_user_id handled_at revision'],
            'family_onboarding_deliveries' => ['family_onboarding_id status attempts next_attempt_at claimed_at queued_at accepted_at last_error_code'],
            'care_requests' => ['family_account_id family_user_id created_by_user_id staff_managed staff_archived_at care_plan_id care_arrangement_id is_system_generated origin ai_support_ticket_id title additional_info scope_of_work time_expectations preferred_response_hours status is_private first_applicant_at first_shortlist_at first_hire_at request_type budget_min budget_max requested_start_at requested_end_at recurring_days recurring_start_time recurring_end_time recurring_schedule recurring_starts_on recurring_ends_on city state zip', 'recurring_days recurring_schedule', 'id family_account_id status is_system_generated'],
            'care_request_applications' => ['care_request_id caregiver_user_id status proposed_rate cover_note selected_days offered_schedule offer_version', 'selected_days offered_schedule'],
            'care_request_invitations' => ['care_request_id family_account_id family_user_id invited_by_user_id caregiver_user_id care_request_application_id status message expires_at responded_at'],
            'care_request_recruitments' => ['care_request_id caregiver_user_id invited_by_user_id status status_reason sent_at'],
            'care_request_conversations' => ['care_request_id family_account_id family_user_id caregiver_user_id care_request_application_id started_by_user_id family_last_read_at staff_last_read_at caregiver_last_read_at last_message_at last_message_sender_id'],
            'care_request_messages' => ['care_request_conversation_id sender_user_id body edited_at'],
            'customer_care_actions' => ['care_request_id actor_user_id action note metadata', 'metadata'],
            'care_request_recipients' => ['care_request_id care_recipient_profile_id care_recipient_profile_version_id full_name recipient_is_requester relationship_to_family mobility_level care_notes'],
            'care_recipient_profile_versions' => ['care_recipient_profile_id version_number created_by_user_id candidate_snapshot assigned_snapshot', 'candidate_snapshot assigned_snapshot'],
            'care_recipient_profiles' => ['family_account_id legacy_family_user_id created_by_user_id updated_by_user_id status recipient_is_requester full_name preferred_name relationship_to_family about_them good_visit_notes communication_preferences communication_notes support_areas support_details mobility_level mobility_notes routine_notes personal_care_preferences sleep_overnight_notes comfort_needs caregiver_quality_preferences caregiver_do_notes caregiver_avoid_notes last_reviewed_at latest_ready_version_id revision archived_at', 'communication_preferences support_areas support_details comfort_needs caregiver_quality_preferences'],
            'care_arrangements' => ['source_care_request_id family_account_id family_user_id title schedule_slots exceptions starts_on ends_on timezone status', 'schedule_slots exceptions'],
            'care_arrangement_needs' => ['care_arrangement_id need_key kind starts_at ends_at status note'],
            'care_relationships' => ['family_account_id family_user_id caregiver_user_id source_care_request_id last_care_request_id last_care_booking_id recipient_name care_recipient_profile_id status last_visit_at metadata', 'metadata'],
            'care_bookings' => ['financial_reference pricing_version family_care_rate_cents family_processing_fee_rate_cents caregiver_gross_rate_cents caregiver_fee_policy pricing_snapshotted_at care_request_id care_plan_id care_arrangement_need_id occurrence_key occurrence_revision plan_visit_kind plan_schedule_version care_request_application_id family_account_id family_user_id caregiver_user_id agreement_snapshot family_terms_accepted_at caregiver_terms_accepted_at check_in_override_at check_in_override_by_user_id check_in_override_reason status scheduled_start_at scheduled_end_at started_at check_in_note paused_at total_paused_seconds completed_at check_out_note timesheet_submitted_at expected_minutes worked_minutes family_confirmed_at family_confirmed_by_user_id dispute_opened_at dispute_opened_by_user_id dispute_reason dispute_status no_show_flag late_cancel_flag reviewed_at cancelled_at cancelled_by_user_id cancellation_reason replacement_released_at last_rescheduled_at last_reschedule_reason', 'agreement_snapshot', 'id care_request_id family_account_id status replacement_released_at completed_at'],
            'care_booking_events' => ['care_booking_id actor_user_id actor_role event_type payload happened_at', 'payload'],
            'care_booking_change_requests' => ['care_booking_id requester_user_id type status reason proposed_start_at proposed_end_at resolved_at resolved_by_user_id resolution_note'],
            'care_booking_time_corrections' => ['care_booking_id requester_user_id family_account_id family_user_id version supersedes_id status reason_code explanation proposed_started_at proposed_completed_at proposed_break_minutes proposed_worked_minutes original_snapshot financial_preview family_response_note approved_by_user_id support_ticket_id processing_attempts last_error submitted_at changes_requested_at approved_at processing_started_at payment_action_required_at first_reminded_at second_reminded_at escalated_at withdrawn_at finalized_at', 'original_snapshot financial_preview'],
            'care_booking_corrections' => ['care_booking_id support_ticket_id actor_admin_user_id source time_correction_request_id requester_user_id approved_by_user_id action status attempt_count previous_charge_cents target_charge_cents payment_delta_cents caregiver_delta_cents family_approval_confirmed_at reason before_snapshot requested_changes after_snapshot last_error booking_applied_at payout_applied_at applied_at', 'before_snapshot requested_changes after_snapshot'],
            'care_booking_incidents' => ['care_booking_id reporter_user_id severity status title description reported_at resolved_at resolved_by_user_id'],
            'care_booking_payments' => ['care_booking_id financial_reference pricing_version worked_minutes family_care_rate_cents family_processing_fee_rate_cents caregiver_gross_rate_cents family_account_id family_user_id initiated_by_user_id caregiver_user_id status currency amount_authorized_cents amount_captured_cents family_care_amount_cents family_processing_fee_cents amount_refunded_cents amount_overage_cents overage_pending_cents platform_fee_cents caregiver_gross_amount_cents stripe_processing_fee_cents caregiver_amount_cents fee_finalization_status fee_finalized_at authorization_expires_at authorized_at reauthorized_at captured_at transferred_at failed_at last_error'],
            'care_booking_payment_attempts' => ['care_booking_payment_id care_booking_id family_account_id care_booking_time_correction_id purpose amount_cents currency status is_active last_error authorized_at captured_at canceled_at superseded_at'],
            'care_booking_payment_operations' => ['care_booking_payment_id care_booking_id family_account_id parent_operation_id financial_reference type status amount_cents currency occurred_at processed_at failed_at last_error'],
            'care_plans' => ['care_arrangement_id care_relationship_id family_account_id family_user_id caregiver_user_id source_care_request_id source_care_booking_id next_booking_id status title care_recipient_profile_id care_notes schedule_days schedule_start_time schedule_end_time schedule_slots starts_on ends_on timezone pause_starts_on resumes_on schedule_version counter_schedule_days counter_schedule_start_time counter_schedule_end_time counter_schedule_slots counter_starts_on counter_note hourly_rate family_message caregiver_note offered_at responded_at accepted_at declined_at activated_at paused_at ended_at expires_at last_generated_at payment_status last_error metadata', 'schedule_days schedule_slots counter_schedule_days counter_schedule_slots metadata'],
            'care_plan_events' => ['care_plan_id actor_user_id event_type reason payload', 'payload'],
            'care_plan_schedule_changes' => ['care_plan_id care_arrangement_need_id requested_by_user_id responded_by_user_id type status effective_on current_schedule proposed_schedule note responded_at', 'current_schedule proposed_schedule'],
            'continuous_coverage_plans' => ['family_account_id family_user_id created_by_user_id status title timezone starts_on ends_on coverage_pattern shift_length_minutes weekly_schedule care_recipient_profile_id task_snapshot care_notes hourly_rate replacement_confirmation_mode marketplace_applications_enabled last_generated_at metadata', 'weekly_schedule task_snapshot metadata'],
            'continuous_coverage_shifts' => ['continuous_coverage_plan_id shift_template_id assigned_caregiver_user_id care_booking_id released_by_user_id occurrence_key status scheduled_start_at scheduled_end_at scheduled_minutes caregiver_accepted_at family_confirmed_at confirmed_at released_at release_reason cancelled_at completed_at metadata', 'metadata'],
            'continuous_coverage_shift_offers' => ['replacement_case_id continuous_coverage_shift_id roster_member_id caregiver_user_id status expires_at responded_at'],
            'continuous_coverage_roster_members' => ['continuous_coverage_plan_id caregiver_user_id invited_by_user_id status role replacement_opt_in eligible_days eligible_shift_types family_approved_at caregiver_accepted_at paused_at removed_at', 'eligible_days eligible_shift_types'],
            'continuous_coverage_replacement_cases' => ['continuous_coverage_shift_id original_caregiver_user_id winning_offer_id status reason opened_at resolved_at'],
            'continuous_coverage_events' => ['continuous_coverage_plan_id continuous_coverage_shift_id actor_user_id event_type payload happened_at', 'payload'],
            'care_hour_accounts' => ['family_account_id care_recipient_profile_id display_name currency active'],
            'care_hour_purchases' => ['care_hour_account_id family_account_id buyer_user_id paid_minutes bonus_minutes total_minutes amount_cents currency status livemode processing_fee_cents amount_reversed_cents dispute_status paid_at issued_at last_reconciled_at correction_state corrected_by_user_id correction_reason correction_requested_at corrected_at last_error'],
            'care_hour_lots' => ['care_hour_account_id care_hour_purchase_id total_minutes paid_minutes bonus_minutes processing_fee_cents status hold_reason issued_at'],
            'care_hour_allocations' => ['care_hour_account_id care_hour_lot_id care_booking_id start_minute minutes status'],
            'care_hour_manual_credits' => ['family_account_id care_hour_lot_id actor_user_id minutes amount_cents reference reason'],
            'care_hour_ledger_entries' => ['care_hour_account_id care_hour_lot_id care_booking_id care_hour_purchase_id actor_user_id type minutes available_delta reserved_delta used_delta held_delta description metadata', 'metadata'],
            'care_hour_booking_fundings' => ['care_booking_id care_hour_account_id selected_by_user_id status planned_minutes reserved_minutes shortfall_minutes shortfall_cents revision currency committed_at last_error'],
            'care_hour_settlements' => ['care_booking_id care_hour_booking_funding_id status revision worked_minutes prepaid_minutes card_minutes amount_due_cents caregiver_gross_cents package_fee_cents card_fee_cents caregiver_net_cents payable_cents paid_cents currency approved_at paid_at last_error'],
            'care_hour_payment_operations' => ['care_hour_booking_funding_id care_hour_settlement_id kind livemode status amount_cents captured_cents refunded_cents processing_fee_cents currency source_purchase_id authorization_expires_at last_error'],
            'caregiver_profiles' => ['user_id slug bio hourly_rate pricing_tier platform_hourly_rate years_experience care_experience_notes care_experience_answered_at certifications_answered_at service_area_zip service_radius_miles is_accepting_new_clients insurance_status insurance_verified_at status screening_legacy_at screening_legacy_access screening_initial_approved_at screening_suspended_at screening_next_due_at review_submitted_at reviewed_at reviewed_by rejection_reason average_rating reviews_count identity_verified_at identity_verification_status identity_verification_checked_at stripe_connect_onboarding_completed_at stripe_charges_enabled stripe_payouts_enabled background_check_verified_at top_caregiver invite_response_rate avg_invite_response_minutes reliability_score completed_bookings_count cancellation_count dispute_count on_time_check_in_count', '', 'id user_id status'],
            'caregiver_crm_records' => ['caregiver_user_id assigned_admin_id priority notes next_action next_follow_up_at version'],
            'caregiver_crm_activities' => ['sms_message_id caregiver_user_id actor_user_id type call_outcome body occurred_at metadata', 'metadata'],
            'caregiver_availabilities' => ['caregiver_profile_id day_of_week start_time end_time'],
            'caregiver_moderation_logs' => ['caregiver_profile_id actor_user_id action note meta', 'meta'],
            'caregiver_profile_versions' => ['caregiver_profile_id user_id snapshot reason', 'snapshot'],
            'caregiver_certifications' => ['caregiver_profile_id caregiver_certification_type_id custom_name issuer issuing_state expires_at verification_status verified_by_user_id verified_at rejection_reason'],
            'caregiver_certification_types' => ['slug label active'],
            'caregiver_experience_types' => ['slug label active'],
            'skills' => ['name'],
            'languages' => ['name'],
            'care_tasks' => ['name'],
            'sms_messages' => ['lead_id caregiver_user_id direction status from_phone to_phone body twilio_status error_code error_message num_media sent_by_user_id received_at sent_at'],
            'voice_ai_calls' => ['admin_user_id direction status to_phone from_phone twilio_call_sid current_step gathered_name gathered_relationship gathered_phone gathered_location gathered_urgency gathered_callback_time gathered_care_needs callback_requested signup_link_requested transcript transcript_text summary metadata started_at answered_at ended_at duration_seconds', 'transcript metadata'],
            'support_tickets' => ['family_account_id family_visibility source responder_mode opener_user_id counterparty_user_id care_request_id care_booking_id category status priority subject description admin_note resolved_at transcript_deleted_at assigned_admin_id claimed_at transferred_to_human_at returned_to_automation_at handoff_reason_code last_public_message_at last_public_message_sender_id'],
            'support_ticket_messages' => ['support_ticket_id sender_user_id kind responder_type body'],
            'support_ticket_activities' => ['support_ticket_id actor_user_id action metadata', 'metadata'],
            'funnel_events' => ['event user_id role entity_type entity_id metadata occurred_at', 'metadata'],
            'marketing_spend_daily' => ['spend_date channel campaign_id campaign_name ad_set_name ad_name spend_cents impressions clicks currency'],
            'partner_leads' => ['partner_profile_id lead_id source_lead_id category contact_name location zip service_context assigned_by_user_id assigned_at sharing_confirmed_at geography_confirmed_at'],
        ];
        $definitions = [];
        foreach ($rows as $name => $row) {
            $definitions[$name] = [
                'fields' => explode(' ', 'id '.$row[0].' created_at updated_at'),
                'json_fields' => self::words($row[1] ?? ''),
                'essential_fields' => self::words($row[2] ?? ''),
                'key' => ['id'],
                'utc_fields' => $name === 'family_welcome_visits' ? ['window_start_at', 'window_end_at', 'confirmed_start_at'] : [],
            ];
        }
        foreach ([
            'caregiver_skill' => ['caregiver_profile_id', 'skill_id'],
            'caregiver_language' => ['caregiver_profile_id', 'language_id'],
            'caregiver_profile_experience' => ['caregiver_profile_id', 'caregiver_experience_type_id'],
            'care_request_task' => ['care_request_id', 'care_task_id'],
        ] as $name => $key) {
            $definitions[$name] = ['fields' => [...$key, 'created_at', 'updated_at'], 'json_fields' => [], 'essential_fields' => [], 'key' => $key, 'utc_fields' => []];
        }

        return $definitions;
    }

    /** Nested JSON accepts only these keys and exported column names, recursively. */
    public static function businessJsonKeys(): array
    {
        $keys = self::words('from to previous before after changes old new value label text content role timestamp time date speaker outcome connected follow_up_at sequence_complete family_outcome family_outcome_label family_attempt_number family_unanswered_attempt_number source merged_lead_id original_lead surviving_lead_before_merge original_welcome_emails lead_id referral_lead_id call_id voice_ai_call_id care_preferences help_needed start_timing best_contact_time care_location additional_services notes facebook meta website_signup attribution campaign campaign_id campaign_name ad_id ad_name adset_id adset_name ad_set_id ad_set_name form_id form_name page_id platform utm_source utm_medium utm_campaign utm_content utm_term landing_path google_sheets sheet_name row_number synced_at care_for recipient relationship care_notes care_preferences welcome_visit visit_date visit_range visit_time timezone full_name preferred_name phone email city state zip name care_recipient tasks task_ids recipient_snapshot schedule day days day_of_week start end start_time end_time starts_at ends_at slots exceptions monday tuesday wednesday thursday friday saturday sunday mon tue wed thu fri sat sun duration minutes rate reason status old_status new_status actor_id field fields message summary note body amount amount_cents currency error_code code request_id booking_id application_id caregiver_id family_id profile_id count retained_lead_id merged_ids submission_id submitted_at appointment_at location urgency callback_time need additional_info support_areas support_details mobility_level companion personal_care housekeeping meals transportation bathing dressing toileting transfers memory_support overnight companionship meal_preparation medication_reminders light_housekeeping help_with_mobility');
        foreach (self::datasets() as $definition) {
            $keys = array_merge($keys, $definition['fields']);
        }
        $keys = array_merge($keys, self::words('automatic event_key entry_point registered_at captured_at intent consent_received requested_contact call_sid transcript_excerpt at skills languages experience certifications mobility bathing_grooming memory_prompts household_help'));

        return array_values(array_unique($keys));
    }

    private static function words(string $words): array
    {
        return $words === '' ? [] : explode(' ', $words);
    }
}
