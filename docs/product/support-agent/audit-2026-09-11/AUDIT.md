# LoLo AI Support — current state and improvement audit

Date: September 11, 2026  
Source baseline: `2329f04b4ffbb315ba7dd211ad04b5dfe7241b8e`  
Scope: existing support chat, Family AI, retained Caregiver answer/navigation support, administrator controls, knowledge, evaluations, and the current care UI. Audit only; no application changes or deployment.

## Assessment

LoLo has a substantial support assistant already. It can explain the product, inspect authorized account state, guide someone to a control, prepare forms, and execute a defined set of actions after a server-generated confirmation. The existing support conversation is also the human handoff channel.

The next investment should be a focused reliability and experience pass. The foundation is useful, but some financial confirmations, ordinary-language interpretation, navigation targets, and operational claims need correction. A complete intent catalog should not be mistaken for dependable end-to-end assistance.

I would address the financial and wrong-action findings before expanding access. Then improve a small set of common journeys until they are consistently easy to complete. Rebuilding the entire bot or changing its model immediately would leave several confirmed application defects untouched.

## What exists today

| Area | Implemented capability | Important boundary |
| --- | --- | --- |
| Support answers | Governed, versioned knowledge; deterministic answers for common questions; model fallback | Correct source content and current published knowledge are separate requirements. |
| Account awareness | Family-owned requests, applicants, visits, hours, payments, profiles and other state readers | Some readers choose the wrong relevant record or lose the exact visit context. |
| Navigation | Registered destination IDs, page targets, highlights, guided tasks and selected completion verifiers | A target existing in HTML does not prove it is visible or useful. |
| Finding care | Care-type choice, one-time/recurring private drafts, missing-detail collection, recap, explicit publication and receipt | A published request still requires caregiver selection and hiring; it is not a booking. |
| Family actions | Bounded profile/request, applicant/hiring, visit/recurring-care and administration operations | Actions are controlled individually and should be confirmed using the same facts and consequences as the main product. |
| Persistent tasks | Ten goal journeys, interruption/continuation, preparation and completion states | Several layers of state and regex routing make detours and ordinary wording fragile. |
| Human support | Same conversation, visible Human help, transfer reasons, preserved history and administrator takeover | Successful transfer state does not prove a person received an alert or responded. |
| Admin operations | Pilot/Everyone/Emergency stop, grants, capability/tool controls, KB publishing, activity, incidents, retention commands | Current production controls and provider-account settings were not inspected with administrator access. |
| Caregiver | Shared answer/navigation foundation exists | The larger operational expansion and its evaluation portfolio are Family-focused; Caregiver parity is deferred. |

The executable catalog contains **324 Family intents**, all mapped to knowledge and marked pilot in the source registry. Its stage counts are **172 Read, 193 Guide, 77 Prepare, 74 Execute and 88 Human**. These stages overlap. The master plan's separate delivery categories are **74 Complete, 163 Assisted and 87 Human transfer**. Neither set is a measured customer success rate or proof that every capability is enabled in production.

The configured model is **GPT-5.6 Luna, low reasoning**, with strict structured output, a 900-token output limit and at most two provider attempts. Business actions are executed by application services, not by free-form model instructions. The default token-rate constants match the current official Luna listing; changing the model without changing the rate catalog would make cost estimates unreliable. [Official Luna documentation](https://developers.openai.com/api/docs/models/gpt-5.6-luna).

Current availability follows **Pilot only / Live for everyone / Emergency stop**. DEC-072 superseded the earlier release-readiness approval machinery. This audit does not propose bringing that machinery back.

## Evidence collected now

- Reviewed the 63 numbered program records, registries, current runtime/action/guidance/knowledge code, and retained evaluation artifacts through coordinated source audits.
- Ran the complete **AI Support + Support** feature selection: **284 tests**. The first run had eight Windows Git trust-environment failures and one historical date-dependent error. All eight environment failures passed unchanged after a process-local Git trust setting. Consolidated result: **283 passing tests; one remaining historical readiness test error**. No application code was changed to obtain that result.
- Ran isolated synthetic probes that reproduced the negated hire request, missing-payment-authorization failure, preparation surviving human handoff, and quote differences. These used in-memory SQLite; no production payments or business records were involved.
- Inspected the production Family support UI after explicit permission. An existing resolved conversation displayed the AI-assistant label. The panel rendered at desktop and mobile sizes, with no horizontal overflow and 44-pixel mobile controls. **No message was sent, no new model response was tested, and no capability was activated.** Opening the existing conversation follows the normal mark-as-read behavior.
- Confirmed four live guidance targets are actually `sr-only` elements measuring **1 × 1 pixel**: Schedule, Care request/Arrangements, Regular care and History. This verifies the hidden-target condition; no production guide was started.
- Restored the browser viewport and signed out.

The historical model evaluation remains useful background: its saved artifact verifies an August 14 baseline. Later retained model rehearsal used prompt v4; source now uses v9. Those scores do not establish current conversational quality. Fresh production availability, published KB versions, provider configuration, live latency, and end-to-end financial flows remain unverified by this audit.

## Priority findings

| Priority | Finding and evidence | Improvement |
| --- | --- | --- |
| High | **A review confirmation can also approve hours and capture payment.** The AI review recap shows rating/comment without the worked-time and amount recap, while the commit path can confirm the timesheet and capture payment. Source-verified; no live payment attempted. | Separate review submission from hours/payment approval, or present the complete combined consequence with exact amounts and explicit consent using the existing payment confirmation service. |
| High | **Financial recap text can disagree with the actual quote.** AI hire text hard-codes $30/hour; the payment path supports platform configuration and legacy snapshots. Synthetic two-hour examples produced $62 under code defaults and $35 under a legacy snapshot versus an AI $60 explanation. These are test examples, not claims about this account's charges. | Generate a single authoritative quote for both UI and chat; bind the confirmation to its amount, rate/snapshot, duration, payer and expiry; recheck it before commit. |
| High | **Negation can propose the opposite action.** “Don't hire anyone. Just show me applicants.” produced a `caregiver.hire` recap. The explicit confirmation boundary prevented a booking; zero bookings were created. | Resolve what the user wants to do and what they prohibit before selecting a tool. Read-only language must not produce a mutation recap. Add the reproduced case and paraphrases to regression tests. |
| High | **Expected payment failures lack a recoverable chat path.** Missing authorization raised `PaymentException`; the confirmation UI catches only `ValidationException`. The recap remains active, with no confirmation receipt. | Translate expected domain errors into a plain explanation and exact recovery action, retain the user's draft, and require a refreshed recap when payment facts change. |
| High | **Guidance can “succeed” on an invisible target.** The four affected target nodes are 1 × 1 pixels in production; frontend arrival checks do not require useful visible geometry. | Put markers on the visible panel/control, verify visible placement before reporting arrival, and use “I opened the page” if highlighting fails. Add real browser visibility assertions. |
| Medium/high | **Bot navigation and state disagree with the current care workspace.** Some answers still teach Actions/Arrangements tabs; generated visits can open the entire plan; a stale in-progress visit can override the actual next visit. | Share the product's presentation/state rules, keep selected request/plan/visit IDs, and distinguish “this visit” from “the repeating schedule” in both links and confirmations. |
| Medium | **Handoff does not fully retire prepared form state.** A prepared form already applied to session state remained consumable after human handoff invalidated its action card. No booking or request was created by the probe. | Revalidate preparation and conversation state at consumption, clear pending session payloads on handoff, and state clearly whether an already opened form remains the user's manual draft. |
| Medium/high | **Admin alerts can overstate containment.** A failed-notification incident can say a capability was stopped even though that branch changes no control. | Store and display severity separately from actual containment; name the stopped capability only after the control change succeeds. |
| Medium | **Operational measurement and retention are incomplete.** Some records receive retention deadlines without a pruning path; no independent last-success heartbeat proves jobs ran. “Conversation P95” is provider-success latency, not the full user experience. | Add honest execution status, overdue counts, delivery queue age, end-to-end timings and verified task-outcome metrics. |

These findings concern action proposals, financial consent, recovery and operational truth. The audit did not reproduce a model bypassing the explicit confirmation boundary or an unauthorized production write.

Detailed source references and reproductions are in [runtime findings](C:/Users/Epicpp/homecare/docs/product/support-agent/audit-2026-09-11/runtime-findings.md), [experience findings](C:/Users/Epicpp/homecare/docs/product/support-agent/audit-2026-09-11/experience-findings.md), and [operations findings](C:/Users/Epicpp/homecare/docs/product/support-agent/audit-2026-09-11/operations-findings.md).

## How I would improve the experience

1. **Make the assistant aware of the exact thing being discussed.** Opening support from a request should bind that request. “These applicants” should retain their request ID. A visit question should retain the dated visit and its parent recurring plan. Ask a short selection question only when that context is genuinely missing.
2. **Offer a small number of useful starts.** For example: Request care, Review caregiver replies, My next visit, Hours or payment, and Talk to a person. Personalized cards should preserve the exact resource behind the card, not turn it into generic text that requires another selection.
3. **Use a clear distinction between explanation, preparation and action.** “Here is what this means,” “I have prepared this,” and “Please confirm this exact change” should look and read differently. Keep one primary next action and a visible way to change or stop the task.
4. **Make recurring care understandable at the decision point.** Show “This visit: Tue, Oct 13” versus “Every Tuesday from Oct 20.” Never make the user infer whether cancelling or changing a time affects one date or the whole plan.
5. **Recover in place.** If a card is missing, authorization expired, the model times out, or a target disappears, preserve progress and offer the next valid control. A normal task difficulty should not immediately become a dead conversation or a forced human transfer.
6. **Keep the current visual foundation and polish clarity.** The mobile panel is usable. Give the full AI identity/availability line room, reduce repeated status banners, and return keyboard focus to the actual opener on pages with inline support controls.

Example desired interaction:

> Family: “I only need to cancel next Monday, not the weekly care.”  
> LoLo: “You mean Monday, October 12, 9–12 with your caregiver. The other weekly visits will stay scheduled. Review this visit's cancellation terms?”  
> The next card shows the exact visit, actual financial consequence if any, and a clearly named confirmation action.

This is a proposed design example, not a statement about a real booking or the current cancellation policy.

## Investment options

| Option | What changes | Benefit and trade-off |
| --- | --- | --- |
| **A — Reliability and alignment first (recommended)** | Fix the confirmed financial, interpretation, recovery, navigation and alert problems; align knowledge with the care UI; refresh focused regression/browser cases. | Smallest scope with the most immediate trust benefit. The existing architecture stays intact. |
| **B — A more natural, context-aware assistant** | Build on A with exact page/resource context, clearer goal state, better negation/detour handling, and a measured structured interpretation layer. | Better conversation quality and fewer repeated questions. More work and model evaluation are needed; every write still uses the existing domain service and confirmation. |
| **C — Expand the product** | Add deeper Caregiver workflows, multilingual help or other new channels once the current Family journeys are dependable. | Broader reach, but substantially more knowledge, role-specific evaluation, support operations and maintenance. It does not fix today's Family defects. |

I recommend A now, followed by B for the five highest-use journeys: creating care, reviewing replies/hiring, changing one recurring visit, reviewing hours/payment, and recovering with human help. Do not infer the highest-use journeys solely from the catalog; confirm the ordering with actual task starts and support demand.

## What to measure

Keep correctness and comprehension ahead of message count. Track: correct first destination; correct action proposed; repeated questions; task completion confirmed by server state; time and turns to complete; understandable failure recovery; abandonment; human transfer delivery/claim; corrections after a confirmed action; and estimated cost per verified resolution.

Use a held-out set of natural multi-turn conversations, including negation, typos, conflicting updates, one-visit-versus-series changes, missing payment methods, stale records, refresh/navigation, and human takeover. Catalog phrase matching remains useful but cannot be the whole quality measure. Continuous task-specific evaluation and human calibration are also recommended by the [official evaluation guidance](https://developers.openai.com/api/docs/guides/evaluation-best-practices).

Preserve the constrained output schema, narrow authorized tools and explicit confirmation boundary. These align with the [official guidance on limiting agent data flow and evaluating safety](https://developers.openai.com/api/docs/guides/agent-builder-safety). Evaluate model or prompting changes on the new corpus before adopting them; several findings here are application logic or UI drift and will persist with a larger model.

## Evidence files and limits

- Full feature run: `.tmp/support-audit-2026-09-11/tests.log` and `junit.xml`.
- Eight environment-only reruns: `.tmp/support-audit-2026-09-11/environment-rerun.log` and `environment-rerun.xml`.
- Catalog summary and exploratory phrasing probe: `.tmp/support-audit-2026-09-11/catalog-probe.json`. Unmatched phrases are **not** end-to-end failures; later runtime handling or a provider fallback can still resolve them.
- Focused runtime reproductions: `.tmp/ai-support-runtime-audit-2026-09-11/`.
- The remaining test error uses an expired August pilot date in a superseded readiness path; see the operations findings for exact source evidence.

No application source, production settings, KB publication or capability controls were changed. No production message, invite, hire, review, approval, or payment was submitted. No live provider benchmark or admin/provider-account inspection was performed. Reports and synthetic probes are local, uncommitted audit artifacts.
