# Family Pilot Browser Correction Batch

Status: Implemented in source; deployment and authenticated production retest pending

Date: August 28, 2026

Scope: Family AI Support only, exact two-user pilot, no Caregiver AI work, no general release, and no payment-domain implementation change

## Outcome

This batch closes the defects found during the authenticated current-layout audit. It keeps language-model use for interpretation while moving the affected high-confidence behaviors into deterministic application paths.

## Implemented behavior

- A user can modify the start time or duration of a completed one-time or recurring private request draft in ordinary language. The draft is updated, the earlier confirmation is invalidated, and a fresh recap is shown.
- Payment history, care-profile information, visit policy, and other read-only questions no longer create unrelated long-lived goals.
- A question about cancelling a visit does not cancel an active guided task. `Stop`, `cancel`, or an explicit request to stop the task still works.
- General visit-cancellation questions explain the 24-hour late-cancellation window and use the exact booking/payment record when one is identifiable. The assistant does not guess a fee, refund, or payment result.
- Care-profile purpose, contents, and visibility questions use `FAM-PROFILE-001` and its governed KB explanation.
- The chat’s route-dependent Livewire and Alpine identity is consistent across in-app navigation. Notifications and Care profiles can wait for and highlight their real page targets without a stale chat instance reporting a false failure.
- A temporary guided destination takes display priority over a saved goal in the collapsed and open chat guide, so users see the instruction for the page they just opened.
- The AI Support pricing truth is $30 per worked hour total for the Family, $27 per worked hour caregiver gross less actual Stripe processing fees, and a $3 per worked hour gross LoLo platform portion. The change is limited to AI prompts, deterministic answers, intent mappings, KB sources, and evaluations.
- `ai-support:activate-batch5-pilot` now enables confirmed one-time and recurring request publication for the exact two active pilot grants. It refuses to run if general release is enabled or if the active cohort is not exactly the configured two users.

## Knowledge publication

`family-experience-alignment-v1` now includes 55 entries, including the two canonical pricing entries and both care-profile overview articles. After deployment, one command updates and publishes all changed Family guidance:

```bash
php artisan ai-support:realign-family-kb \
  --publish \
  --actor-email=test@test.com \
  --reason="Publish the Family pilot browser corrections and approved 30/27/3 AI Support pricing truth."
```

The command changes KB versions only. It does not change pilot access, care requests, profiles, visits, payments, or general availability.

Then enable confirmed request creation for the same two pilot users:

```bash
php artisan ai-support:activate-batch5-pilot --actor-email=test@test.com
```

## Required production retest

Keep **Live for everyone** off and test with a pilot Family account:

1. Complete a recurring private draft, choose **Modify something**, and say `I want to change the start time to 10:00 AM.` A fresh recap must show 10:00 AM.
2. During care-type help, ask `How do I view my payment history?` It must answer the new question instead of repeating the care-type choice.
3. Ask `What happens if I need to cancel a booked visit?` It must explain the cancellation window without stopping the current guide or transferring automatically.
4. Ask `What is a care profile and what should it contain?` It must explain the purpose and then offer the current Care profiles page.
5. Open Notifications and Care profiles from chat. Both pages must highlight the intended area without a false missing-target message or Alpine undefined-variable error.
6. Ask `How much does care cost for two hours?` The Family total must be $60, caregiver gross $54 less actual Stripe processing fees, and LoLo gross portion $6.
7. Complete a request recap. The **Confirm and create request** button must appear. Only click it for a clearly marked test request, then remove that test request after verification.

## Source verification

- PHP formatting: passed for all changed PHP files.
- Production frontend build: passed.
- AI Support and Support feature suites: **278 tests / 6,229 assertions passed**.
- Focused correction suite: **119 tests / 3,443 assertions passed**.
- Family intent plan: **324 / 324** catalog records, **324 / 324** explicit KB mappings, **1,296** phrases, and routing precheck passed with zero provider calls.
- `FamilyExperienceKnowledgeAlignmentTest` verifies the expanded 55-entry realignment package and idempotent publication behavior in an isolated database.

## Rollback

- Use the Admin **Emergency stop** for immediate AI shutdown.
- Keep **Pilot only** selected to prevent exposure beyond the two configured users.
- Disable `capability.care_request_publish_v1`, `commit.one_time`, `commit.recurring`, and the two care-request publish tools to return to recap-only request creation without disabling support answers.
