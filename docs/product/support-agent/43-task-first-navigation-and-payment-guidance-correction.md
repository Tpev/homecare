# Task-First Navigation and Payment Guidance Correction

Status: Implemented and locally verified

Date: August 18, 2026

Trigger: Live two-user Family pilot screenshot showing repeated generic instructions after “Hi, I want to use another credit card” and “I’m the account owner”

## Finding

The assistant understood the general subject but failed the user’s task. The initial sentence did not match the deterministic payment-method router because it used **use another credit card** instead of the previously covered add/change/update/replace phrases. The message therefore reached the general model and governed product-fact path, which returned generic Account-owner wording without reading the signed-in user’s authorized billing state or creating a navigation action.

The next sentence, **“I’m the account owner,”** was processed as a standalone answer. The runtime did not reconnect that short follow-up to the earlier payment request, so the assistant repeated instructions and said it could not verify ownership even though the application has authorized Family-account context.

This was technically grounded prose but poor assistance. For a clear, supported task, the assistant should use application state and present the next action instead of describing menus repeatedly.

## Corrected behavior

1. **Use another/different/new card** and equivalent wording route directly to the deterministic payment-method service.
2. The service reads only the signed-in user’s authorized Family billing summary and chooses **Add payment method** or **Update payment method** from current state.
3. It creates the existing guided action for `family.billing.payment_method`; it never sends card details or this intent to the model.
4. The user clicks the action, reaches `/family/billing`, and receives the existing focus/highlight instruction for the secure card button.
5. Short follow-ups such as **I’m the account owner**, **yes please**, **take me there**, or **open it** recover payment context from the current user’s recent messages and the ticket opener. The application still verifies real access; it never trusts the ownership claim itself.
6. The existing conversation shown in the screenshot can therefore recover after deployment without starting a new chat.
7. General model-driven navigation is now task-first: when a user clearly wants to complete a supported task on one authorized semantic target, the model may propose the allowlisted navigation action even if the sentence did not literally contain open/show/find/go. Pure fact questions remain answers.
8. A navigation proposal remains a user-clicked button. It never claims the task succeeded and cannot use an arbitrary URL, selector, coordinate, role, or resource.

## Safety and authorization unchanged

- Billing access is re-read from the authenticated Family Account.
- Only the server’s `NavigationTargetRegistry` can produce the destination URL.
- The assistant does not click the secure Stripe control, collect card data, or change a payment method.
- Completion is reported only after the existing billing return flow verifies current state.
- Cross-account access, expired guidance, missing/disabled targets, cancel, failure, and human takeover continue to fail safely.
- The correction does not expand the pilot cohort or change Pilot/Everyone availability.

## Implementation

- `AiSupportGuidedTaskService`: expanded direct intent phrases and added actor-scoped contextual follow-up recovery.
- `AiSupportRuntimeService`: uses contextual payment intent and accepts an authorized `navigate` result without a literal navigation verb; the allowlist and role checks remain mandatory.
- `AiSupportRuntimePromptBuilder`: `interactive-support-v5` distinguishes a task that needs a next-step button from a purely factual question.
- `family-guided-v1.php`: freezes the exact production wording as the 122nd routing phrase.
- `GuidedPaymentMethodTest`: locks the direct screenshot phrase and existing-conversation follow-up, including zero provider calls.
- `InteractiveSupportRuntimeTest`: locks task-implied allowlisted navigation and the existing write/safety boundaries.
- Daily turn/cost monitoring now uses the application’s Eastern-Time day boundary, matching how interaction timestamps are stored and avoiding a short midnight-UTC undercount window.

## Verification

| Check | Result |
| --- | --- |
| Exact “use another credit card” route | Pass; **Update payment method** action, correct target, zero model calls |
| “I’m the account owner” contextual recovery | Pass; **Update payment method** action, correct target, zero model calls |
| Payment guidance and checkout safety suite | 15 passed |
| Interactive runtime and prompt contract | 19 passed |
| Combined focused assertions | 34 tests, 217 assertions, all passed |
| Full Batch 1/2 mass evaluation | 122/122 phrases, 10/10 collisions, 40/40 intents, 32 tests and 376 assertions, all passed |
| Complete AI Support feature suite | 136 tests, 1,196 assertions, all passed |
| Provider / production writes during mass evaluation | Zero / zero |

## Deployment check

Deploy with the normal `deploy.sh`; there is no migration, environment-variable change, seed, or data rewrite.

Using the same Family pilot account and conversation:

1. Send **“I’m the account owner. Take me there.”** again, or send **“I want to use another credit card.”**
2. Confirm the reply contains **Update payment method** when a card is present, or **Add payment method** when none is present.
3. Click it and confirm Billing & Payments opens with the secure Add/Update card control focused and highlighted.
4. Do not enter card data into chat; use only the existing secure billing control.
