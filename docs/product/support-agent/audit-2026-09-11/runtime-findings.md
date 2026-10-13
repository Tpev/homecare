# AI support runtime/backend audit — 2026-09-11

Scope: local source and isolated synthetic execution. No application files changed, no external provider calls, no production access and no live database writes. Configuration describes what the implementation can do; this audit does not establish which controls are enabled in production.

## Implemented capabilities and safeguards

- **Availability fails closed.** Deployment availability, master/user-visible controls, human-only mode, role, exact-user pilot grant or general-release role bundle, capability and registered tool switches participate in eligibility. A control-store error denies access. See [AiSupportEligibilityService.php:15](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportEligibilityService.php:15).
- **Most family work is deterministic.** The runtime prioritizes safety screening, active journeys, guided tasks, pricing, intent resolution and family action services before an optional model call. Model operations are limited to answer/navigation/handoff/care-path/private request patch/private profile patch. The schema exposes no arbitrary write tool. Caregiver runtime is limited to answers and permitted navigation. See [AiSupportRuntimeService.php:39](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportRuntimeService.php:39), [AiSupportOpenAiClient.php:134](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportOpenAiClient.php:134).
- **Family writes are substantial:** request creation/withdrawal; profile editing/readiness/default/archive; invitations, applicant decisions and messaging; hiring; visit changes/cancellation/no-show/completion/hours/time corrections/reviews/rebooking; recurring offers/schedule changes/extra visits/pause/resume/end; account name/verification, family access and notification preferences. They are conditional registered capabilities, not unrestricted model tools. See [config/ai_support.php:60](C:/Users/Epicpp/homecare/config/ai_support.php:60).
- **Confirmation is bound to server state.** Preview creation checks ticket access, registered tool/version and current eligibility. Random references are hashed; actor binding, expiry, row locks, current eligibility and idempotency are rechecked for commit. Domain operations generally re-query authorized records and compare expected status/version. Preview contents are encrypted and erased on commit. See [AiSupportActionEvidenceService.php:28](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportActionEvidenceService.php:28), [FamilyCareOperationsActionService.php:2010](C:/Users/Epicpp/homecare/app/Services/AiSupport/FamilyCareOperationsActionService.php:2010).
- **Publishing validates the draft again.** Draft owner/account/ticket/version/material hash, profile ready-version and task availability are checked before creating an ordinary request. It creates no booking/payment. Private publication is not in the draft schema: publication is public, with that outcome disclosed in recap. See [AiSupportCareRequestPublisher.php:29](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportCareRequestPublisher.php:29).
- **Navigation is an allowlist, not model-supplied URLs or selectors.** Resource navigation checks the family account or conversation access. Model-visible targets omit resource-specific destinations it cannot bind. See [NavigationTargetRegistry.php:37](C:/Users/Epicpp/homecare/app/Services/AiSupport/NavigationTargetRegistry.php:37).
- **Human handoff is durable.** The ticket becomes human-only under lock; previews, unconsumed action cards and guided tasks are invalidated. Admin notification failure does not restart automation; an incident is opened. A person must deliberately return the ticket to automation. See [AiSupportHandoffService.php:34](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportHandoffService.php:34).
- **Completion is not inferred from arrival or “done.”** Payment-method, payment-attention and request-receipt verifiers inspect authoritative state. Unknown/generic verifiers return unverified. Domain writes produce persisted evidence. See [AiSupportCompletionVerifierRegistry.php:16](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportCompletionVerifierRegistry.php:16).

## Prioritized findings

### R1 — P1: Financial confirmations use a different pricing source from actual payment

**Verified in source and synthetic calculation.** AI pricing assumes $30/hour total and zero family processing fee. Hire, hours approval and recurring-offer recaps also hard-code that total; hours approval calculates minutes × $30. The real payment service uses current pricing, booking snapshots and agreements. With the code-default $30 care + $1 fee, two hours is **$62** while AI says **$60**. A synthetic $17.50/hour snapshot produces **$35**, still versus AI's **$60**. These are configuration/snapshot examples, not a claim about current production prices.

Evidence: [AiSupportPricingTruth.php:7](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportPricingTruth.php:7), [hire recap:354](C:/Users/Epicpp/homecare/app/Services/AiSupport/FamilyCareOperationsActionService.php:354), [hours recap:627](C:/Users/Epicpp/homecare/app/Services/AiSupport/FamilyCareOperationsActionService.php:627), [real quote:165](C:/Users/Epicpp/homecare/app/Support/MarketplacePricing.php:165), [payment quotation:41](C:/Users/Epicpp/homecare/app/Services/Payments/BookingPaymentService.php:41). The model instructions also hard-code $30 and forbid personalized pricing at [AiSupportRuntimePromptBuilder.php:25](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportRuntimePromptBuilder.php:25).

Recommendation: use the same authoritative pair/booking quote for the visible recap and material confirmation, including duration, processing fee and total; require a refreshed recap when its quote changes. Update governed pricing answers and model context from that same source.

### R2 — P1: A review confirmation can also approve hours and capture payment without a financial recap

**Verified in source; no payment executed.** FAM-VISIT-030 selects completed/reviewed visits regardless of family approval. Its recap shows caregiver, rating and comment, with only generic wording that payment “may process.” `commitReview()` captures payment when family approval is absent, then sets `family_confirmed_at`. The user does not see worked hours or a charge amount in this review confirmation.

Evidence: [review preparation:678](C:/Users/Epicpp/homecare/app/Services/AiSupport/FamilyCareOperationsActionService.php:678), [review commit:1568](C:/Users/Epicpp/homecare/app/Services/AiSupport/FamilyCareOperationsActionService.php:1568). This is a material disclosure mismatch, not evidence of a write without pressing Confirm.

Recommendation: require verified hours approval before the review action, or explicitly combine the two with the complete hours/payment recap and accurate amount.

### R3 — P2: Negated hiring intent becomes a hire proposal

**Verified through the full runtime with one eligible applicant.** “Don't hire anyone. Just show me applicants.” generated a `domain_recap` for `caregiver.hire`, titled “Hire Synthetic Audit Caregiver?”. Request/application stayed open/applied and no booking was created. Confirmation protects the final write, but the proposed next step contradicts the user's explicit instruction.

The resolver's FAM-MATCH-020 result is passed to the action service; the downstream branch has no negation check and selects the sole eligible applicant when no name is supplied. Evidence: [runtime dispatch:209](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportRuntimeService.php:209), [hire branch:315](C:/Users/Epicpp/homecare/app/Services/AiSupport/FamilyCareOperationsActionService.php:315), [applicant selection:1825](C:/Users/Epicpp/homecare/app/Services/AiSupport/FamilyCareOperationsActionService.php:1825).

Recommendation: resolve negation and read-only intent before action intent; cover “don't,” “not yet,” “just show” and questions about an action in regression scenarios.

### R4 — P2: Expected payment failures escape the chat's recovery UI

**Verified through confirmation service, with UI propagation verified in source.** Confirming a valid hours recap for a legacy booking without payment authorization throws `PaymentException`, retains the active recap and leaves family approval unset. `ChatWidget::confirmDomainAction()` catches only `ValidationException`; the normal confirmation-failed event is therefore not dispatched for this known billing condition. An ordinary visit screen explicitly catches this payment exception.

Evidence: [payment error:636](C:/Users/Epicpp/homecare/app/Services/Payments/BookingPaymentService.php:636), [AI capture:1536](C:/Users/Epicpp/homecare/app/Services/AiSupport/FamilyCareOperationsActionService.php:1536), [exception type](C:/Users/Epicpp/homecare/app/Exceptions/Payments/PaymentException.php:7), [chat catch:368](C:/Users/Epicpp/homecare/app/Livewire/Support/ChatWidget.php:368).

Recommendation: translate expected payment/domain failures into a recoverable chat result with the authoritative message and exact secure billing/visit destination; retain idempotent evidence for uncertain outcomes. Also review deterministic runtime paths before the provider try/catch for equivalent unhandled service errors.

### R5 — P2: Already-opened preparations survive human handoff

**Verified with a synthetic prepared form.** Prepare → apply → human handoff invalidates the action card, but the underlying `AiSupportPreparation` remains `applied`. The existing session reference still allows `consume()` to return its fields while the ticket is human-only. `consume()` checks actor/contract/expiry/resource but not current ticket mode, family-account access or eligibility. This does not auto-save a domain object; it can resurrect stale form data after automation has stopped.

Evidence: [handoff invalidation list:76](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportHandoffService.php:76), [consume:155](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportPreparationService.php:155), [usable-state check:36](C:/Users/Epicpp/homecare/app/Models/AiSupportPreparation.php:36).

Recommendation: cancel corresponding preparations during handoff/control stop/logout and re-authorize consumption against the current account/ticket. Clear an invalid session reference. Preserve editable user changes already in a form rather than silently restoring old values.

### R6 — P2: The fabricated-success guard covers wording, not factual success

**Guard behavior verified locally; no real model attack tested.** The guard blocks “I created your request” but allows “Your request has been published” and “Your card has been charged.” It also matches quoted/question text containing “I created.” The answer delivery branch accepts a retrieved KB reference or an active draft, without checking a claimed success against action evidence. Thus prompt instructions carry most protection for unsupported success claims; existing mocked success tests cover only the narrow blocked wording. A matched phrase also stops the entire answers capability.

Evidence: [guard:559](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportRuntimeService.php:559), [answer delivery:515](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportRuntimeService.php:515), [global stop:380](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportRuntimeService.php:380).

Recommendation: reserve success/status statements for server-rendered evidence-backed responses; keep model prose descriptive. Expand adversarial evaluation to paraphrases, quotations and mixed instructions, and avoid treating a single phrasing match as reliable evidence of a system-wide incident.

### R7 — P3: The human-return reason is requested, validated and discarded

**Verified in source.** `returnToAutomation()` requires a 5–500-character explanation, but the transaction does not capture `$reason` and its event records only `admin_deliberate_return`. The administrator and transition are recorded; the requested rationale is not.

Evidence: [AiSupportHandoffService.php:191](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportHandoffService.php:191). Recommendation: retain the rationale in the appropriate restricted audit record, keeping content-free interaction telemetry separate.

## Privacy and residual risks

- Provider requests set `store=false` and use an HMAC safety identifier. Public conversation text is assembled; internal notes are excluded. Draft/preparation/preview fields are encrypted locally, and event metadata is constrained. These controls do not mean the provider receives no personal data.
- On a care-relevant fallback turn, family context includes household address and **home access notes**; the prompt includes them plus recent public conversation and active draft/profile fields. Consider intent-specific minimization, particularly omitting door/access instructions for general product questions. Sources: [runtime context trigger:325](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportRuntimeService.php:325), [household payload:103](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportFamilyContextService.php:103), [prompt input:55](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportRuntimePromptBuilder.php:55), [provider store flag:19](C:/Users/Epicpp/homecare/app/Services/AiSupport/AiSupportOpenAiClient.php:19). No cross-account disclosure was demonstrated.
- Ordinary control stops and grant revocation change ticket mode and block late message delivery. A grant expiring during a provider request has no corresponding mode change; delivery checks mode/closed/deleted state but not renewed eligibility. This is an **unreproduced timing risk**, not a demonstrated bypass of the normal stop controls.
- Domain commit/evidence and chat receipt creation are separate transactions. A failure or handoff between them can leave a committed action without its chat receipt. Idempotency protects against duplicate writes, but recovery should reconcile from evidence rather than imply failure. Sources: [action commit:116](C:/Users/Epicpp/homecare/app/Services/AiSupport/FamilyCareOperationsActionService.php:116), [receipt transaction:124](C:/Users/Epicpp/homecare/app/Services/AiSupport/FamilyCareOperationsActionService.php:124). This race was not reproduced.
- No finding establishes that a model can bypass the final server confirmation, act across family accounts or perform a caregiver write. Prompt-injection resistance of live provider behavior was not evaluated; HTTP was faked throughout.

## Verification evidence

Existing bounded tests: `php artisan test --compact tests/Feature/AiSupport/RuntimeSafetyFoundationTest.php tests/Feature/AiSupport/InteractiveSupportRuntimeTest.php` — **26 passed, 186 assertions**. [Log](C:/Users/Epicpp/homecare/.tmp/ai-support-runtime-audit-2026-09-11/runtime-tests.log).

Additional standalone probes booted Laravel against explicitly checked `sqlite :memory:`, migrated only that in-memory database, faked HTTP/mail/notifications, and used synthetic records. No production credentials or provider were used. No new application/test files were created.

- [Preparation, pricing and claim-guard probe](C:/Users/Epicpp/homecare/.tmp/ai-support-runtime-audit-2026-09-11/probes.log): human-only ticket with still-consumable preparation; $62/$35 authoritative examples versus $60 AI; exact guard results; zero care requests/bookings created.
- [Full-runtime negation and payment-error probe](C:/Users/Epicpp/homecare/.tmp/ai-support-runtime-audit-2026-09-11/negation-payment-probes.log): unwanted hire recap without commit; uncaught payment exception with no confirmed evidence or family approval.

The root audit runs the broader AI Support/Support suite separately; these test totals must not be added to its overlapping total.
