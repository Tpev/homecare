# Capability Specification: `CARE-REQUEST-006` — Validate and Present the Request Recap

Status: Implemented and evaluated; release disabled

Version: 1.0

Owner: Family care product

Required release reviewers: Product, engineering, design/accessibility, security/privacy, support operations

Last reviewed: August 14, 2026

Implementation evidence: [Interactive assistant implementation and release evidence](../24-interactive-assistant-implementation-and-release-evidence.md)

## 1. User outcome

An authorized Family user can review every material request detail in a deterministic recap, change any section, understand what publication will do, and obtain a short-lived server-bound confirmation.

This Class C capability does not publish a request.

## 2. Preconditions

- Current `CARE-REQUEST-005` draft belongs to the actor/Family/conversation
- Draft is unexpired and passes current deterministic validation
- Current authorization, pilot, automated ownership, and capability controls pass
- Relevant authoritative recipient/task/profile values resolve

## 3. Recap content

The server renders:

- One-time or regular/recurring request type
- Who needs care
- Approved tasks and relevant notes
- Explicit date/schedule, duration, and Eastern Time
- Full service address
- Additional/access instructions that will be shared through the care workflow
- Caregiver response preference
- What happens next: request becomes live and eligible Caregivers can see it; no Caregiver is hired and no payment is authorized

Pricing appears only after the separate pricing-service reconciliation releases the hold in `DEC-049`. Until then, do not display assistant-computed totals or a contradictory production quote.

Primary controls:

- **Modify something**
- **Confirm and create request**

Do not use conversational **yes**, **okay**, or **continue** as the initial commit control.

## 4. Modification behavior

**Modify something** exposes a **Change** control for:

- Who needs care
- Help needed
- Schedule
- Address
- Additional instructions
- Caregiver response time

Natural-language changes are also accepted. Update only intended fields, collect newly required information, revalidate, and show the complete fresh recap.

Every material change increments the draft version and invalidates the earlier confirmation. A request-type change clears or explicitly remaps incompatible schedule fields.

## 5. Confirmation reference

Issue an opaque single-use reference bound to:

- Actor and Family Account
- Support conversation
- Capability/tool version
- Draft ID and exact version
- Hash of every material normalized field
- Relevant authoritative record versions
- Request type
- 30-minute expiration
- Idempotency context

Store only the reference hash. The model/client cannot create or extend it.

Invalidate on material change, human transfer, logout, Family authorization change, pilot revocation/expiry, capability/tool shutdown, superseding recap, discard, draft expiry, or successful commit.

## 6. Easy expiry renewal

When 30 minutes expires, replace the action with:

> **Review and confirm again**

One activation reloads the current seven-day draft, reauthorizes, re-resolves authoritative data, revalidates, and shows a fresh complete recap and confirmation. Do not require re-entry or make the user find an old chat turn. If one authoritative value changed, identify and open that section while preserving all valid fields.

## 7. Failure behavior

- Invalid draft: open the exact section requiring correction.
- Unauthorized/stale record: do not reveal another record; remove the invalid selection.
- Provider/model outage: recap remains deterministic and should not require a new model call.
- Human transfer: invalidate confirmation and hand off the draft summary.
- Repeated recap failure: preserve draft and offer manual form/person.

## 8. Events and metrics

Record recap generated, section changed, validation result, confirmation issued/invalidated/expired/renewed, and fallback. Metrics include correction rate by section, recap-to-confirm time, expiry/renewal, comprehension, latency, and abandonment.

## 9. Evaluation and release

Zero-tolerance cases include stale draft, changed task/profile, two tabs, type change, expired reference, logout, removed member, revoked grant, disabled tool, human transfer, client-edited recap, fabricated reference, and pricing-hold bypass.

Gates:

- 100% deterministic field accuracy relative to normalized draft
- 100% confirmation binding/invalidation/renewal cases
- Zero pricing shown before the reconciliation gate
- Universal observed comprehension of live-versus-hired and payment timing in the five-person usability gate
- Passing 200% zoom, keyboard, focus, screen-reader, contrast, and touch-target checks
