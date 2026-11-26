# Reporting export field reference

Generated from `ReportingCatalog.php`; edit the catalog, then regenerate this file.

Schema version: 1. Datasets: 78.

All rows are exported. Absent optional tables and columns are declared in the manifest. Missing essential fields reject the snapshot. Each present dataset must also contain its key fields. `created_at`/`updated_at` are included only where the source table retains them. JSON retains only approved nested keys; secrets, URLs and unknown keys are omitted/redacted. Counts of omitted keys and redacted strings appear at each dataset's end.

## users

Key: `id`.

Fields: `id`, `name`, `email`, `phone`, `role`, `city`, `state`, `onboarding_completed_at`, `access_revoked_at`, `sms_opt_in`, `sms_opted_in_at`, `created_at`, `updated_at`.

Essential fields: `id`, `role`, `access_revoked_at`.

## leads

Key: `id`.

Fields: `id`, `family_account_id`, `lead_type`, `name`, `email`, `phone`, `company`, `location`, `zip`, `data`, `status`, `assigned_admin_id`, `priority`, `source`, `source_detail`, `external_source`, `external_id`, `contact_role`, `submitted_at`, `first_call_at`, `first_connected_at`, `call_attempt_count`, `unanswered_attempt_count`, `last_contacted_at`, `next_follow_up_at`, `converted_at`, `do_not_contact_at`, `closed_reason`, `created_at`, `updated_at`.

Structured JSON fields: `data`.

Essential fields: `id`, `lead_type`, `status`, `family_account_id`.

## lead_external_identities

Key: `id`.

Fields: `id`, `lead_id`, `external_source`, `external_id`, `created_at`, `updated_at`.

## lead_activities

Key: `id`.

Fields: `id`, `lead_id`, `sms_message_id`, `actor_user_id`, `type`, `summary`, `body`, `occurred_at`, `metadata`, `created_at`, `updated_at`.

Structured JSON fields: `metadata`.

Essential fields: `id`, `lead_id`, `type`, `body`, `occurred_at`.

## lead_welcome_emails

Key: `id`.

Fields: `id`, `lead_id`, `email`, `status`, `reason`, `subject`, `body`, `attempts`, `last_attempt_at`, `sent_at`, `previewed_at`, `unsubscribed_at`, `account_user_id`, `account_created_at`, `account_linked_at`, `care_request_id`, `request_posted_at`, `created_at`, `updated_at`.

## family_accounts

Key: `id`.

Fields: `id`, `owner_user_id`, `status`, `default_care_recipient_profile_id`, `payment_method_status`, `payment_method_checked_at`, `payment_method_refresh_attempted_at`, `payment_method_snapshot_version`, `created_at`, `updated_at`.

Essential fields: `id`, `owner_user_id`.

## family_payment_setup_links

Key: `id`.

Fields: `id`, `family_account_id`, `created_by_user_id`, `expires_at`, `revoked_at`, `completed_at`, `consented_at`, `consent_version`, `checkout_attempt`, `created_at`, `updated_at`.

## family_recipient_profiles

Key: `id`.

Fields: `id`, `family_account_id`, `family_user_id`, `recipient_is_requester`, `full_name`, `mobility_level`, `relationship_to_family`, `care_notes`, `created_at`, `updated_at`.

## family_household_profiles

Key: `id`.

Fields: `id`, `family_account_id`, `family_user_id`, `city`, `state`, `zip`, `time_expectations`, `preferred_response_hours`, `created_at`, `updated_at`.

## family_account_members

Key: `id`.

Fields: `id`, `family_account_id`, `user_id`, `access_level`, `status`, `joined_at`, `ended_at`, `ended_by_user_id`, `created_at`, `updated_at`.

## family_account_activity_logs

Key: `id`.

Fields: `id`, `family_account_id`, `actor_user_id`, `action`, `subject_user_id`, `metadata`, `created_at`, `updated_at`.

Structured JSON fields: `metadata`.

## family_onboardings

Key: `id`.

Fields: `id`, `family_account_id`, `initiated_by_user_id`, `completed_by_user_id`, `care_recipient_profile_id`, `source`, `status`, `exemption_reason`, `schema_version`, `current_step`, `revision`, `draft`, `request_context`, `submitted_snapshot`, `started_at`, `completed_at`, `request_handoff_completed_at`, `created_at`, `updated_at`.

Structured JSON fields: `draft`, `request_context`, `submitted_snapshot`.

## family_welcome_visits

Key: `id`.

Fields: `id`, `family_onboarding_id`, `preferred_date`, `preferred_range`, `timezone`, `window_start_at`, `window_end_at`, `phone`, `status`, `confirmed_start_at`, `staff_note`, `handled_by_user_id`, `handled_at`, `revision`, `created_at`, `updated_at`.

UTC-stored instants: `window_start_at`, `window_end_at`, `confirmed_start_at`.

## family_onboarding_deliveries

Key: `id`.

Fields: `id`, `family_onboarding_id`, `status`, `attempts`, `next_attempt_at`, `claimed_at`, `queued_at`, `accepted_at`, `last_error_code`, `created_at`, `updated_at`.

## care_requests

Key: `id`.

Fields: `id`, `family_account_id`, `family_user_id`, `created_by_user_id`, `staff_managed`, `staff_archived_at`, `care_plan_id`, `care_arrangement_id`, `is_system_generated`, `origin`, `ai_support_ticket_id`, `title`, `additional_info`, `scope_of_work`, `time_expectations`, `preferred_response_hours`, `status`, `is_private`, `first_applicant_at`, `first_shortlist_at`, `first_hire_at`, `request_type`, `budget_min`, `budget_max`, `requested_start_at`, `requested_end_at`, `recurring_days`, `recurring_start_time`, `recurring_end_time`, `recurring_schedule`, `recurring_starts_on`, `recurring_ends_on`, `city`, `state`, `zip`, `created_at`, `updated_at`.

Structured JSON fields: `recurring_days`, `recurring_schedule`.

Essential fields: `id`, `family_account_id`, `status`, `is_system_generated`.

## care_request_applications

Key: `id`.

Fields: `id`, `care_request_id`, `caregiver_user_id`, `status`, `proposed_rate`, `cover_note`, `selected_days`, `offered_schedule`, `offer_version`, `created_at`, `updated_at`.

Structured JSON fields: `selected_days`, `offered_schedule`.

## care_request_invitations

Key: `id`.

Fields: `id`, `care_request_id`, `family_account_id`, `family_user_id`, `invited_by_user_id`, `caregiver_user_id`, `care_request_application_id`, `status`, `message`, `expires_at`, `responded_at`, `created_at`, `updated_at`.

## care_request_recruitments

Key: `id`.

Fields: `id`, `care_request_id`, `caregiver_user_id`, `invited_by_user_id`, `status`, `status_reason`, `sent_at`, `created_at`, `updated_at`.

## care_request_conversations

Key: `id`.

Fields: `id`, `care_request_id`, `family_account_id`, `family_user_id`, `caregiver_user_id`, `care_request_application_id`, `started_by_user_id`, `family_last_read_at`, `staff_last_read_at`, `caregiver_last_read_at`, `last_message_at`, `last_message_sender_id`, `created_at`, `updated_at`.

## care_request_messages

Key: `id`.

Fields: `id`, `care_request_conversation_id`, `sender_user_id`, `body`, `edited_at`, `created_at`, `updated_at`.

## customer_care_actions

Key: `id`.

Fields: `id`, `care_request_id`, `actor_user_id`, `action`, `note`, `metadata`, `created_at`, `updated_at`.

Structured JSON fields: `metadata`.

## care_request_recipients

Key: `id`.

Fields: `id`, `care_request_id`, `care_recipient_profile_id`, `care_recipient_profile_version_id`, `full_name`, `recipient_is_requester`, `relationship_to_family`, `mobility_level`, `care_notes`, `created_at`, `updated_at`.

## care_recipient_profile_versions

Key: `id`.

Fields: `id`, `care_recipient_profile_id`, `version_number`, `created_by_user_id`, `candidate_snapshot`, `assigned_snapshot`, `created_at`, `updated_at`.

Structured JSON fields: `candidate_snapshot`, `assigned_snapshot`.

## care_recipient_profiles

Key: `id`.

Fields: `id`, `family_account_id`, `legacy_family_user_id`, `created_by_user_id`, `updated_by_user_id`, `status`, `recipient_is_requester`, `full_name`, `preferred_name`, `relationship_to_family`, `about_them`, `good_visit_notes`, `communication_preferences`, `communication_notes`, `support_areas`, `support_details`, `mobility_level`, `mobility_notes`, `routine_notes`, `personal_care_preferences`, `sleep_overnight_notes`, `comfort_needs`, `caregiver_quality_preferences`, `caregiver_do_notes`, `caregiver_avoid_notes`, `last_reviewed_at`, `latest_ready_version_id`, `revision`, `archived_at`, `created_at`, `updated_at`.

Structured JSON fields: `communication_preferences`, `support_areas`, `support_details`, `comfort_needs`, `caregiver_quality_preferences`.

## care_arrangements

Key: `id`.

Fields: `id`, `source_care_request_id`, `family_account_id`, `family_user_id`, `title`, `schedule_slots`, `exceptions`, `starts_on`, `ends_on`, `timezone`, `status`, `created_at`, `updated_at`.

Structured JSON fields: `schedule_slots`, `exceptions`.

## care_arrangement_needs

Key: `id`.

Fields: `id`, `care_arrangement_id`, `need_key`, `kind`, `starts_at`, `ends_at`, `status`, `note`, `created_at`, `updated_at`.

## care_relationships

Key: `id`.

Fields: `id`, `family_account_id`, `family_user_id`, `caregiver_user_id`, `source_care_request_id`, `last_care_request_id`, `last_care_booking_id`, `recipient_name`, `care_recipient_profile_id`, `status`, `last_visit_at`, `metadata`, `created_at`, `updated_at`.

Structured JSON fields: `metadata`.

## care_bookings

Key: `id`.

Fields: `id`, `financial_reference`, `pricing_version`, `family_care_rate_cents`, `family_processing_fee_rate_cents`, `caregiver_gross_rate_cents`, `caregiver_fee_policy`, `pricing_snapshotted_at`, `care_request_id`, `care_plan_id`, `care_arrangement_need_id`, `occurrence_key`, `occurrence_revision`, `plan_visit_kind`, `plan_schedule_version`, `care_request_application_id`, `family_account_id`, `family_user_id`, `caregiver_user_id`, `agreement_snapshot`, `family_terms_accepted_at`, `caregiver_terms_accepted_at`, `check_in_override_at`, `check_in_override_by_user_id`, `check_in_override_reason`, `status`, `scheduled_start_at`, `scheduled_end_at`, `started_at`, `check_in_note`, `paused_at`, `total_paused_seconds`, `completed_at`, `check_out_note`, `timesheet_submitted_at`, `expected_minutes`, `worked_minutes`, `family_confirmed_at`, `family_confirmed_by_user_id`, `dispute_opened_at`, `dispute_opened_by_user_id`, `dispute_reason`, `dispute_status`, `no_show_flag`, `late_cancel_flag`, `reviewed_at`, `cancelled_at`, `cancelled_by_user_id`, `cancellation_reason`, `replacement_released_at`, `last_rescheduled_at`, `last_reschedule_reason`, `created_at`, `updated_at`.

Structured JSON fields: `agreement_snapshot`.

Essential fields: `id`, `care_request_id`, `family_account_id`, `status`, `replacement_released_at`, `completed_at`.

## care_booking_events

Key: `id`.

Fields: `id`, `care_booking_id`, `actor_user_id`, `actor_role`, `event_type`, `payload`, `happened_at`, `created_at`, `updated_at`.

Structured JSON fields: `payload`.

## care_booking_change_requests

Key: `id`.

Fields: `id`, `care_booking_id`, `requester_user_id`, `type`, `status`, `reason`, `proposed_start_at`, `proposed_end_at`, `resolved_at`, `resolved_by_user_id`, `resolution_note`, `created_at`, `updated_at`.

## care_booking_time_corrections

Key: `id`.

Fields: `id`, `care_booking_id`, `requester_user_id`, `family_account_id`, `family_user_id`, `version`, `supersedes_id`, `status`, `reason_code`, `explanation`, `proposed_started_at`, `proposed_completed_at`, `proposed_break_minutes`, `proposed_worked_minutes`, `original_snapshot`, `financial_preview`, `family_response_note`, `approved_by_user_id`, `support_ticket_id`, `processing_attempts`, `last_error`, `submitted_at`, `changes_requested_at`, `approved_at`, `processing_started_at`, `payment_action_required_at`, `first_reminded_at`, `second_reminded_at`, `escalated_at`, `withdrawn_at`, `finalized_at`, `created_at`, `updated_at`.

Structured JSON fields: `original_snapshot`, `financial_preview`.

## care_booking_corrections

Key: `id`.

Fields: `id`, `care_booking_id`, `support_ticket_id`, `actor_admin_user_id`, `source`, `time_correction_request_id`, `requester_user_id`, `approved_by_user_id`, `action`, `status`, `attempt_count`, `previous_charge_cents`, `target_charge_cents`, `payment_delta_cents`, `caregiver_delta_cents`, `family_approval_confirmed_at`, `reason`, `before_snapshot`, `requested_changes`, `after_snapshot`, `last_error`, `booking_applied_at`, `payout_applied_at`, `applied_at`, `created_at`, `updated_at`.

Structured JSON fields: `before_snapshot`, `requested_changes`, `after_snapshot`.

## care_booking_incidents

Key: `id`.

Fields: `id`, `care_booking_id`, `reporter_user_id`, `severity`, `status`, `title`, `description`, `reported_at`, `resolved_at`, `resolved_by_user_id`, `created_at`, `updated_at`.

## care_booking_payments

Key: `id`.

Fields: `id`, `care_booking_id`, `financial_reference`, `pricing_version`, `worked_minutes`, `family_care_rate_cents`, `family_processing_fee_rate_cents`, `caregiver_gross_rate_cents`, `family_account_id`, `family_user_id`, `initiated_by_user_id`, `caregiver_user_id`, `status`, `currency`, `amount_authorized_cents`, `amount_captured_cents`, `family_care_amount_cents`, `family_processing_fee_cents`, `amount_refunded_cents`, `amount_overage_cents`, `overage_pending_cents`, `platform_fee_cents`, `caregiver_gross_amount_cents`, `stripe_processing_fee_cents`, `caregiver_amount_cents`, `fee_finalization_status`, `fee_finalized_at`, `authorization_expires_at`, `authorized_at`, `reauthorized_at`, `captured_at`, `transferred_at`, `failed_at`, `last_error`, `created_at`, `updated_at`.

## care_booking_payment_attempts

Key: `id`.

Fields: `id`, `care_booking_payment_id`, `care_booking_id`, `family_account_id`, `care_booking_time_correction_id`, `purpose`, `amount_cents`, `currency`, `status`, `is_active`, `last_error`, `authorized_at`, `captured_at`, `canceled_at`, `superseded_at`, `created_at`, `updated_at`.

## care_booking_payment_operations

Key: `id`.

Fields: `id`, `care_booking_payment_id`, `care_booking_id`, `family_account_id`, `parent_operation_id`, `financial_reference`, `type`, `status`, `amount_cents`, `currency`, `occurred_at`, `processed_at`, `failed_at`, `last_error`, `created_at`, `updated_at`.

## care_plans

Key: `id`.

Fields: `id`, `care_arrangement_id`, `care_relationship_id`, `family_account_id`, `family_user_id`, `caregiver_user_id`, `source_care_request_id`, `source_care_booking_id`, `next_booking_id`, `status`, `title`, `care_recipient_profile_id`, `care_notes`, `schedule_days`, `schedule_start_time`, `schedule_end_time`, `schedule_slots`, `starts_on`, `ends_on`, `timezone`, `pause_starts_on`, `resumes_on`, `schedule_version`, `counter_schedule_days`, `counter_schedule_start_time`, `counter_schedule_end_time`, `counter_schedule_slots`, `counter_starts_on`, `counter_note`, `hourly_rate`, `family_message`, `caregiver_note`, `offered_at`, `responded_at`, `accepted_at`, `declined_at`, `activated_at`, `paused_at`, `ended_at`, `expires_at`, `last_generated_at`, `payment_status`, `last_error`, `metadata`, `created_at`, `updated_at`.

Structured JSON fields: `schedule_days`, `schedule_slots`, `counter_schedule_days`, `counter_schedule_slots`, `metadata`.

## care_plan_events

Key: `id`.

Fields: `id`, `care_plan_id`, `actor_user_id`, `event_type`, `reason`, `payload`, `created_at`, `updated_at`.

Structured JSON fields: `payload`.

## care_plan_schedule_changes

Key: `id`.

Fields: `id`, `care_plan_id`, `care_arrangement_need_id`, `requested_by_user_id`, `responded_by_user_id`, `type`, `status`, `effective_on`, `current_schedule`, `proposed_schedule`, `note`, `responded_at`, `created_at`, `updated_at`.

Structured JSON fields: `current_schedule`, `proposed_schedule`.

## continuous_coverage_plans

Key: `id`.

Fields: `id`, `family_account_id`, `family_user_id`, `created_by_user_id`, `status`, `title`, `timezone`, `starts_on`, `ends_on`, `coverage_pattern`, `shift_length_minutes`, `weekly_schedule`, `care_recipient_profile_id`, `task_snapshot`, `care_notes`, `hourly_rate`, `replacement_confirmation_mode`, `marketplace_applications_enabled`, `last_generated_at`, `metadata`, `created_at`, `updated_at`.

Structured JSON fields: `weekly_schedule`, `task_snapshot`, `metadata`.

## continuous_coverage_shifts

Key: `id`.

Fields: `id`, `continuous_coverage_plan_id`, `shift_template_id`, `assigned_caregiver_user_id`, `care_booking_id`, `released_by_user_id`, `occurrence_key`, `status`, `scheduled_start_at`, `scheduled_end_at`, `scheduled_minutes`, `caregiver_accepted_at`, `family_confirmed_at`, `confirmed_at`, `released_at`, `release_reason`, `cancelled_at`, `completed_at`, `metadata`, `created_at`, `updated_at`.

Structured JSON fields: `metadata`.

## continuous_coverage_shift_offers

Key: `id`.

Fields: `id`, `replacement_case_id`, `continuous_coverage_shift_id`, `roster_member_id`, `caregiver_user_id`, `status`, `expires_at`, `responded_at`, `created_at`, `updated_at`.

## continuous_coverage_roster_members

Key: `id`.

Fields: `id`, `continuous_coverage_plan_id`, `caregiver_user_id`, `invited_by_user_id`, `status`, `role`, `replacement_opt_in`, `eligible_days`, `eligible_shift_types`, `family_approved_at`, `caregiver_accepted_at`, `paused_at`, `removed_at`, `created_at`, `updated_at`.

Structured JSON fields: `eligible_days`, `eligible_shift_types`.

## continuous_coverage_replacement_cases

Key: `id`.

Fields: `id`, `continuous_coverage_shift_id`, `original_caregiver_user_id`, `winning_offer_id`, `status`, `reason`, `opened_at`, `resolved_at`, `created_at`, `updated_at`.

## continuous_coverage_events

Key: `id`.

Fields: `id`, `continuous_coverage_plan_id`, `continuous_coverage_shift_id`, `actor_user_id`, `event_type`, `payload`, `happened_at`, `created_at`, `updated_at`.

Structured JSON fields: `payload`.

## care_hour_accounts

Key: `id`.

Fields: `id`, `family_account_id`, `care_recipient_profile_id`, `display_name`, `currency`, `active`, `created_at`, `updated_at`.

## care_hour_purchases

Key: `id`.

Fields: `id`, `care_hour_account_id`, `family_account_id`, `buyer_user_id`, `paid_minutes`, `bonus_minutes`, `total_minutes`, `amount_cents`, `currency`, `status`, `livemode`, `processing_fee_cents`, `amount_reversed_cents`, `dispute_status`, `paid_at`, `issued_at`, `last_reconciled_at`, `correction_state`, `corrected_by_user_id`, `correction_reason`, `correction_requested_at`, `corrected_at`, `last_error`, `created_at`, `updated_at`.

## care_hour_lots

Key: `id`.

Fields: `id`, `care_hour_account_id`, `care_hour_purchase_id`, `total_minutes`, `paid_minutes`, `bonus_minutes`, `processing_fee_cents`, `status`, `hold_reason`, `issued_at`, `created_at`, `updated_at`.

## care_hour_allocations

Key: `id`.

Fields: `id`, `care_hour_account_id`, `care_hour_lot_id`, `care_booking_id`, `start_minute`, `minutes`, `status`, `created_at`, `updated_at`.

## care_hour_manual_credits

Key: `id`.

Fields: `id`, `family_account_id`, `care_hour_lot_id`, `actor_user_id`, `minutes`, `amount_cents`, `reference`, `reason`, `created_at`, `updated_at`.

## care_hour_ledger_entries

Key: `id`.

Fields: `id`, `care_hour_account_id`, `care_hour_lot_id`, `care_booking_id`, `care_hour_purchase_id`, `actor_user_id`, `type`, `minutes`, `available_delta`, `reserved_delta`, `used_delta`, `held_delta`, `description`, `metadata`, `created_at`, `updated_at`.

Structured JSON fields: `metadata`.

## care_hour_booking_fundings

Key: `id`.

Fields: `id`, `care_booking_id`, `care_hour_account_id`, `selected_by_user_id`, `status`, `planned_minutes`, `reserved_minutes`, `shortfall_minutes`, `shortfall_cents`, `revision`, `currency`, `committed_at`, `last_error`, `created_at`, `updated_at`.

## care_hour_settlements

Key: `id`.

Fields: `id`, `care_booking_id`, `care_hour_booking_funding_id`, `status`, `revision`, `worked_minutes`, `prepaid_minutes`, `card_minutes`, `amount_due_cents`, `caregiver_gross_cents`, `package_fee_cents`, `card_fee_cents`, `caregiver_net_cents`, `payable_cents`, `paid_cents`, `currency`, `approved_at`, `paid_at`, `last_error`, `created_at`, `updated_at`.

## care_hour_payment_operations

Key: `id`.

Fields: `id`, `care_hour_booking_funding_id`, `care_hour_settlement_id`, `kind`, `livemode`, `status`, `amount_cents`, `captured_cents`, `refunded_cents`, `processing_fee_cents`, `currency`, `source_purchase_id`, `authorization_expires_at`, `last_error`, `created_at`, `updated_at`.

## caregiver_profiles

Key: `id`.

Fields: `id`, `user_id`, `slug`, `bio`, `hourly_rate`, `pricing_tier`, `platform_hourly_rate`, `years_experience`, `care_experience_notes`, `care_experience_answered_at`, `certifications_answered_at`, `service_area_zip`, `service_radius_miles`, `is_accepting_new_clients`, `insurance_status`, `insurance_verified_at`, `status`, `screening_legacy_at`, `screening_legacy_access`, `screening_initial_approved_at`, `screening_suspended_at`, `screening_next_due_at`, `review_submitted_at`, `reviewed_at`, `reviewed_by`, `rejection_reason`, `average_rating`, `reviews_count`, `identity_verified_at`, `identity_verification_status`, `identity_verification_checked_at`, `stripe_connect_onboarding_completed_at`, `stripe_charges_enabled`, `stripe_payouts_enabled`, `background_check_verified_at`, `top_caregiver`, `invite_response_rate`, `avg_invite_response_minutes`, `reliability_score`, `completed_bookings_count`, `cancellation_count`, `dispute_count`, `on_time_check_in_count`, `created_at`, `updated_at`.

Essential fields: `id`, `user_id`, `status`.

## caregiver_crm_records

Key: `id`.

Fields: `id`, `caregiver_user_id`, `assigned_admin_id`, `priority`, `notes`, `next_action`, `next_follow_up_at`, `version`, `created_at`, `updated_at`.

## caregiver_crm_activities

Key: `id`.

Fields: `id`, `sms_message_id`, `caregiver_user_id`, `actor_user_id`, `type`, `call_outcome`, `body`, `occurred_at`, `metadata`, `created_at`, `updated_at`.

Structured JSON fields: `metadata`.

## caregiver_availabilities

Key: `id`.

Fields: `id`, `caregiver_profile_id`, `day_of_week`, `start_time`, `end_time`, `created_at`, `updated_at`.

## caregiver_moderation_logs

Key: `id`.

Fields: `id`, `caregiver_profile_id`, `actor_user_id`, `action`, `note`, `meta`, `created_at`, `updated_at`.

Structured JSON fields: `meta`.

## caregiver_profile_versions

Key: `id`.

Fields: `id`, `caregiver_profile_id`, `user_id`, `snapshot`, `reason`, `created_at`, `updated_at`.

Structured JSON fields: `snapshot`.

## caregiver_certifications

Key: `id`.

Fields: `id`, `caregiver_profile_id`, `caregiver_certification_type_id`, `custom_name`, `issuer`, `issuing_state`, `expires_at`, `verification_status`, `verified_by_user_id`, `verified_at`, `rejection_reason`, `created_at`, `updated_at`.

## caregiver_certification_types

Key: `id`.

Fields: `id`, `slug`, `label`, `active`, `created_at`, `updated_at`.

## caregiver_experience_types

Key: `id`.

Fields: `id`, `slug`, `label`, `active`, `created_at`, `updated_at`.

## skills

Key: `id`.

Fields: `id`, `name`, `created_at`, `updated_at`.

## languages

Key: `id`.

Fields: `id`, `name`, `created_at`, `updated_at`.

## care_tasks

Key: `id`.

Fields: `id`, `name`, `created_at`, `updated_at`.

## sms_messages

Key: `id`.

Fields: `id`, `lead_id`, `caregiver_user_id`, `direction`, `status`, `from_phone`, `to_phone`, `body`, `twilio_status`, `error_code`, `error_message`, `num_media`, `sent_by_user_id`, `received_at`, `sent_at`, `created_at`, `updated_at`.

## voice_ai_calls

Key: `id`.

Fields: `id`, `admin_user_id`, `direction`, `status`, `to_phone`, `from_phone`, `twilio_call_sid`, `current_step`, `gathered_name`, `gathered_relationship`, `gathered_phone`, `gathered_location`, `gathered_urgency`, `gathered_callback_time`, `gathered_care_needs`, `callback_requested`, `signup_link_requested`, `transcript`, `transcript_text`, `summary`, `metadata`, `started_at`, `answered_at`, `ended_at`, `duration_seconds`, `created_at`, `updated_at`.

Structured JSON fields: `transcript`, `metadata`.

## support_tickets

Key: `id`.

Fields: `id`, `family_account_id`, `family_visibility`, `source`, `responder_mode`, `opener_user_id`, `counterparty_user_id`, `care_request_id`, `care_booking_id`, `category`, `status`, `priority`, `subject`, `description`, `admin_note`, `resolved_at`, `transcript_deleted_at`, `assigned_admin_id`, `claimed_at`, `transferred_to_human_at`, `returned_to_automation_at`, `handoff_reason_code`, `last_public_message_at`, `last_public_message_sender_id`, `created_at`, `updated_at`.

## support_ticket_messages

Key: `id`.

Fields: `id`, `support_ticket_id`, `sender_user_id`, `kind`, `responder_type`, `body`, `created_at`, `updated_at`.

## support_ticket_activities

Key: `id`.

Fields: `id`, `support_ticket_id`, `actor_user_id`, `action`, `metadata`, `created_at`, `updated_at`.

Structured JSON fields: `metadata`.

## funnel_events

Key: `id`.

Fields: `id`, `event`, `user_id`, `role`, `entity_type`, `entity_id`, `metadata`, `occurred_at`, `created_at`, `updated_at`.

Structured JSON fields: `metadata`.

## marketing_spend_daily

Key: `id`.

Fields: `id`, `spend_date`, `channel`, `campaign_id`, `campaign_name`, `ad_set_name`, `ad_name`, `spend_cents`, `impressions`, `clicks`, `currency`, `created_at`, `updated_at`.

## partner_leads

Key: `id`.

Fields: `id`, `partner_profile_id`, `lead_id`, `source_lead_id`, `category`, `contact_name`, `location`, `zip`, `service_context`, `assigned_by_user_id`, `assigned_at`, `sharing_confirmed_at`, `geography_confirmed_at`, `created_at`, `updated_at`.

## caregiver_skill

Key: `caregiver_profile_id`, `skill_id`.

Fields: `caregiver_profile_id`, `skill_id`, `created_at`, `updated_at`.

## caregiver_language

Key: `caregiver_profile_id`, `language_id`.

Fields: `caregiver_profile_id`, `language_id`, `created_at`, `updated_at`.

## caregiver_profile_experience

Key: `caregiver_profile_id`, `caregiver_experience_type_id`.

Fields: `caregiver_profile_id`, `caregiver_experience_type_id`, `created_at`, `updated_at`.

## care_request_task

Key: `care_request_id`, `care_task_id`.

Fields: `care_request_id`, `care_task_id`, `created_at`, `updated_at`.

## Approved nested JSON keys

Applied recursively to objects; arrays keep their order and scalar values. Unknown object keys are counted and omitted. Free text remains complete except for link/credential redaction. This protects structured snapshots from accidentally exposing newly added fields.

`about_them`, `accepted_at`, `access_level`, `access_revoked_at`, `account_created_at`, `account_linked_at`, `account_user_id`, `action`, `activated_at`, `active`, `actor_admin_user_id`, `actor_id`, `actor_role`, `actor_user_id`, `ad_id`, `ad_name`, `ad_set_id`, `ad_set_name`, `additional_info`, `additional_services`, `admin_note`, `admin_user_id`, `adset_id`, `adset_name`, `after`, `after_snapshot`, `agreement_snapshot`, `ai_support_ticket_id`, `amount`, `amount_authorized_cents`, `amount_captured_cents`, `amount_cents`, `amount_due_cents`, `amount_overage_cents`, `amount_refunded_cents`, `amount_reversed_cents`, `answered_at`, `application_id`, `applied_at`, `appointment_at`, `approved_at`, `approved_by_user_id`, `archived_at`, `assigned_admin_id`, `assigned_at`, `assigned_by_user_id`, `assigned_caregiver_user_id`, `assigned_snapshot`, `at`, `attempt_count`, `attempts`, `attribution`, `authorization_expires_at`, `authorized_at`, `automatic`, `available_delta`, `average_rating`, `avg_invite_response_minutes`, `background_check_verified_at`, `bathing`, `bathing_grooming`, `before`, `before_snapshot`, `best_contact_time`, `bio`, `body`, `bonus_minutes`, `booking_applied_at`, `booking_id`, `budget_max`, `budget_min`, `buyer_user_id`, `call_attempt_count`, `call_id`, `call_outcome`, `call_sid`, `callback_requested`, `callback_time`, `campaign`, `campaign_id`, `campaign_name`, `canceled_at`, `cancellation_count`, `cancellation_reason`, `cancelled_at`, `cancelled_by_user_id`, `candidate_snapshot`, `captured_at`, `captured_cents`, `card_fee_cents`, `card_minutes`, `care_arrangement_id`, `care_arrangement_need_id`, `care_booking_id`, `care_booking_payment_id`, `care_booking_time_correction_id`, `care_experience_answered_at`, `care_experience_notes`, `care_for`, `care_hour_account_id`, `care_hour_booking_funding_id`, `care_hour_lot_id`, `care_hour_purchase_id`, `care_hour_settlement_id`, `care_location`, `care_notes`, `care_plan_id`, `care_preferences`, `care_recipient`, `care_recipient_profile_id`, `care_recipient_profile_version_id`, `care_relationship_id`, `care_request_application_id`, `care_request_conversation_id`, `care_request_id`, `care_task_id`, `caregiver_accepted_at`, `caregiver_amount_cents`, `caregiver_avoid_notes`, `caregiver_certification_type_id`, `caregiver_delta_cents`, `caregiver_do_notes`, `caregiver_experience_type_id`, `caregiver_fee_policy`, `caregiver_gross_amount_cents`, `caregiver_gross_cents`, `caregiver_gross_rate_cents`, `caregiver_id`, `caregiver_last_read_at`, `caregiver_net_cents`, `caregiver_note`, `caregiver_profile_id`, `caregiver_quality_preferences`, `caregiver_terms_accepted_at`, `caregiver_user_id`, `category`, `certifications`, `certifications_answered_at`, `changes`, `changes_requested_at`, `channel`, `check_in_note`, `check_in_override_at`, `check_in_override_by_user_id`, `check_in_override_reason`, `check_out_note`, `checkout_attempt`, `city`, `claimed_at`, `clicks`, `closed_reason`, `code`, `comfort_needs`, `committed_at`, `communication_notes`, `communication_preferences`, `companion`, `companionship`, `company`, `completed_at`, `completed_bookings_count`, `completed_by_user_id`, `confirmed_at`, `confirmed_start_at`, `connected`, `consent_received`, `consent_version`, `consented_at`, `contact_name`, `contact_role`, `content`, `continuous_coverage_plan_id`, `continuous_coverage_shift_id`, `converted_at`, `corrected_at`, `corrected_by_user_id`, `correction_reason`, `correction_requested_at`, `correction_state`, `count`, `counter_note`, `counter_schedule_days`, `counter_schedule_end_time`, `counter_schedule_slots`, `counter_schedule_start_time`, `counter_starts_on`, `counterparty_user_id`, `cover_note`, `coverage_pattern`, `created_at`, `created_by_user_id`, `currency`, `current_schedule`, `current_step`, `custom_name`, `data`, `date`, `day`, `day_of_week`, `days`, `declined_at`, `default_care_recipient_profile_id`, `description`, `direction`, `display_name`, `dispute_count`, `dispute_opened_at`, `dispute_opened_by_user_id`, `dispute_reason`, `dispute_status`, `do_not_contact_at`, `draft`, `dressing`, `duration`, `duration_seconds`, `edited_at`, `effective_on`, `eligible_days`, `eligible_shift_types`, `email`, `end`, `end_time`, `ended_at`, `ended_by_user_id`, `ends_at`, `ends_on`, `entity_id`, `entity_type`, `entry_point`, `error_code`, `error_message`, `escalated_at`, `event`, `event_key`, `event_type`, `exceptions`, `exemption_reason`, `expected_minutes`, `experience`, `expires_at`, `explanation`, `external_id`, `external_source`, `facebook`, `failed_at`, `family_account_id`, `family_approval_confirmed_at`, `family_approved_at`, `family_attempt_number`, `family_care_amount_cents`, `family_care_rate_cents`, `family_confirmed_at`, `family_confirmed_by_user_id`, `family_id`, `family_last_read_at`, `family_message`, `family_onboarding_id`, `family_outcome`, `family_outcome_label`, `family_processing_fee_cents`, `family_processing_fee_rate_cents`, `family_response_note`, `family_terms_accepted_at`, `family_unanswered_attempt_number`, `family_user_id`, `family_visibility`, `fee_finalization_status`, `fee_finalized_at`, `field`, `fields`, `finalized_at`, `financial_preview`, `financial_reference`, `first_applicant_at`, `first_call_at`, `first_connected_at`, `first_hire_at`, `first_reminded_at`, `first_shortlist_at`, `follow_up_at`, `form_id`, `form_name`, `fri`, `friday`, `from`, `from_phone`, `full_name`, `gathered_callback_time`, `gathered_care_needs`, `gathered_location`, `gathered_name`, `gathered_phone`, `gathered_relationship`, `gathered_urgency`, `geography_confirmed_at`, `good_visit_notes`, `google_sheets`, `handled_at`, `handled_by_user_id`, `handoff_reason_code`, `happened_at`, `held_delta`, `help_needed`, `help_with_mobility`, `hold_reason`, `hourly_rate`, `household_help`, `housekeeping`, `id`, `identity_verification_checked_at`, `identity_verification_status`, `identity_verified_at`, `impressions`, `initiated_by_user_id`, `insurance_status`, `insurance_verified_at`, `intent`, `invite_response_rate`, `invited_by_user_id`, `is_accepting_new_clients`, `is_active`, `is_private`, `is_system_generated`, `issued_at`, `issuer`, `issuing_state`, `joined_at`, `kind`, `label`, `landing_path`, `language_id`, `languages`, `last_attempt_at`, `last_care_booking_id`, `last_care_request_id`, `last_contacted_at`, `last_error`, `last_error_code`, `last_generated_at`, `last_message_at`, `last_message_sender_id`, `last_public_message_at`, `last_public_message_sender_id`, `last_reconciled_at`, `last_reschedule_reason`, `last_rescheduled_at`, `last_reviewed_at`, `last_visit_at`, `late_cancel_flag`, `latest_ready_version_id`, `lead_id`, `lead_type`, `legacy_family_user_id`, `light_housekeeping`, `livemode`, `location`, `marketplace_applications_enabled`, `meal_preparation`, `meals`, `medication_reminders`, `memory_prompts`, `memory_support`, `merged_ids`, `merged_lead_id`, `message`, `meta`, `metadata`, `minutes`, `mobility`, `mobility_level`, `mobility_notes`, `mon`, `monday`, `name`, `need`, `need_key`, `new`, `new_status`, `next_action`, `next_attempt_at`, `next_booking_id`, `next_follow_up_at`, `no_show_flag`, `note`, `notes`, `num_media`, `occurred_at`, `occurrence_key`, `occurrence_revision`, `offer_version`, `offered_at`, `offered_schedule`, `old`, `old_status`, `on_time_check_in_count`, `onboarding_completed_at`, `opened_at`, `opener_user_id`, `origin`, `original_caregiver_user_id`, `original_lead`, `original_snapshot`, `original_welcome_emails`, `outcome`, `overage_pending_cents`, `overnight`, `owner_user_id`, `package_fee_cents`, `page_id`, `paid_at`, `paid_cents`, `paid_minutes`, `parent_operation_id`, `partner_profile_id`, `pause_starts_on`, `paused_at`, `payable_cents`, `payload`, `payment_action_required_at`, `payment_delta_cents`, `payment_method_checked_at`, `payment_method_refresh_attempted_at`, `payment_method_snapshot_version`, `payment_method_status`, `payment_status`, `payout_applied_at`, `personal_care`, `personal_care_preferences`, `phone`, `plan_schedule_version`, `plan_visit_kind`, `planned_minutes`, `platform`, `platform_fee_cents`, `platform_hourly_rate`, `preferred_date`, `preferred_name`, `preferred_range`, `preferred_response_hours`, `prepaid_minutes`, `previewed_at`, `previous`, `previous_charge_cents`, `pricing_snapshotted_at`, `pricing_tier`, `pricing_version`, `priority`, `processed_at`, `processing_attempts`, `processing_fee_cents`, `processing_started_at`, `profile_id`, `proposed_break_minutes`, `proposed_completed_at`, `proposed_end_at`, `proposed_rate`, `proposed_schedule`, `proposed_start_at`, `proposed_started_at`, `proposed_worked_minutes`, `purpose`, `queued_at`, `rate`, `reason`, `reason_code`, `reauthorized_at`, `received_at`, `recipient`, `recipient_is_requester`, `recipient_name`, `recipient_snapshot`, `recurring_days`, `recurring_end_time`, `recurring_ends_on`, `recurring_schedule`, `recurring_start_time`, `recurring_starts_on`, `reference`, `referral_lead_id`, `refunded_cents`, `registered_at`, `rejection_reason`, `relationship`, `relationship_to_family`, `release_reason`, `released_at`, `released_by_user_id`, `reliability_score`, `removed_at`, `replacement_case_id`, `replacement_confirmation_mode`, `replacement_opt_in`, `replacement_released_at`, `reported_at`, `reporter_user_id`, `request_context`, `request_handoff_completed_at`, `request_id`, `request_posted_at`, `request_type`, `requested_by_user_id`, `requested_changes`, `requested_contact`, `requested_end_at`, `requested_start_at`, `requester_user_id`, `reserved_delta`, `reserved_minutes`, `resolution_note`, `resolved_at`, `resolved_by_user_id`, `responded_at`, `responded_by_user_id`, `responder_mode`, `responder_type`, `resumes_on`, `retained_lead_id`, `returned_to_automation_at`, `review_submitted_at`, `reviewed_at`, `reviewed_by`, `reviews_count`, `revision`, `revoked_at`, `role`, `roster_member_id`, `routine_notes`, `row_number`, `sat`, `saturday`, `schedule`, `schedule_days`, `schedule_end_time`, `schedule_slots`, `schedule_start_time`, `schedule_version`, `scheduled_end_at`, `scheduled_minutes`, `scheduled_start_at`, `schema_version`, `scope_of_work`, `screening_initial_approved_at`, `screening_legacy_access`, `screening_legacy_at`, `screening_next_due_at`, `screening_suspended_at`, `second_reminded_at`, `selected_by_user_id`, `selected_days`, `sender_user_id`, `sent_at`, `sent_by_user_id`, `sequence_complete`, `service_area_zip`, `service_context`, `service_radius_miles`, `severity`, `sharing_confirmed_at`, `sheet_name`, `shift_length_minutes`, `shift_template_id`, `shortfall_cents`, `shortfall_minutes`, `signup_link_requested`, `skill_id`, `skills`, `sleep_overnight_notes`, `slots`, `slug`, `sms_message_id`, `sms_opt_in`, `sms_opted_in_at`, `snapshot`, `source`, `source_care_booking_id`, `source_care_request_id`, `source_detail`, `source_lead_id`, `source_purchase_id`, `speaker`, `spend_cents`, `spend_date`, `staff_archived_at`, `staff_last_read_at`, `staff_managed`, `staff_note`, `start`, `start_minute`, `start_time`, `start_timing`, `started_at`, `started_by_user_id`, `starts_at`, `starts_on`, `state`, `status`, `status_reason`, `stripe_charges_enabled`, `stripe_connect_onboarding_completed_at`, `stripe_payouts_enabled`, `stripe_processing_fee_cents`, `subject`, `subject_user_id`, `submission_id`, `submitted_at`, `submitted_snapshot`, `summary`, `sun`, `sunday`, `superseded_at`, `supersedes_id`, `support_areas`, `support_details`, `support_ticket_id`, `surviving_lead_before_merge`, `synced_at`, `target_charge_cents`, `task_ids`, `task_snapshot`, `tasks`, `text`, `thu`, `thursday`, `time`, `time_correction_request_id`, `time_expectations`, `timesheet_submitted_at`, `timestamp`, `timezone`, `title`, `to`, `to_phone`, `toileting`, `top_caregiver`, `total_minutes`, `total_paused_seconds`, `transcript`, `transcript_deleted_at`, `transcript_excerpt`, `transcript_text`, `transferred_at`, `transferred_to_human_at`, `transfers`, `transportation`, `tue`, `tuesday`, `twilio_call_sid`, `twilio_status`, `type`, `unanswered_attempt_count`, `unsubscribed_at`, `updated_at`, `updated_by_user_id`, `urgency`, `used_delta`, `user_id`, `utm_campaign`, `utm_content`, `utm_medium`, `utm_source`, `utm_term`, `value`, `verification_status`, `verified_at`, `verified_by_user_id`, `version`, `version_number`, `visit_date`, `visit_range`, `visit_time`, `voice_ai_call_id`, `website_signup`, `wed`, `wednesday`, `weekly_schedule`, `welcome_visit`, `window_end_at`, `window_start_at`, `winning_offer_id`, `withdrawn_at`, `worked_minutes`, `years_experience`, `zip`.
