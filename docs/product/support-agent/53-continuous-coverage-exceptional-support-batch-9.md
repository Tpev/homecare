# Continuous Coverage and Exceptional Support — Batch 9

Status: Source implementation complete; automated verification complete; production deployment, KB publication, pilot activation, and authenticated pilot audit pending; **Live for everyone remains off**

Implemented: August 20, 2026

Scope: `FAM-COVERAGE-001` through `FAM-COVERAGE-026` and `FAM-SUPPORT-001` through `FAM-SUPPORT-020`

## Outcome

Batch 9 makes the assistant a reliable front door for Continuous Coverage, complaints, privacy, safety, fraud, unsupported workflows, and other exceptional outcomes. It recognizes the need, reads only the minimum authorized state useful to the human, preserves the conversation and structured context, and transfers ownership without making the user start again.

This batch intentionally does **not** automate Continuous Coverage plan creation, caregiver assignment, live-plan changes, cancellations, pricing exceptions, disputes, safety findings, privacy determinations, refunds, or account recovery. Those outcomes need human judgment or product workflows that are not authorized for AI execution.

## Continuous Coverage behavior

All 26 `FAM-COVERAGE-*` intents follow one deterministic contract:

1. Distinguish a 24/7 or Continuous Coverage need from an immediate emergency.
2. If there is immediate danger, instruct the user to call 911 before transferring.
3. Otherwise read only the latest authorized Continuous Coverage plan summary and current active-shift count when available.
4. Do not interrogate the user or delay transfer for optional information.
5. Transfer the same support conversation with the transcript and structured authorized summary.
6. Say only that the human will reply in the conversation—never promise a queue position, wait time, staffing availability, caregiver acceptance, or resolution.
7. Keep the user able to continue writing in the same conversation.

This covers understanding and starting 24/7 help, status, schedule and caregiver questions, changes, absences, replacements, pauses, cancellations, urgent gaps, billing/receipt questions, coordination, and complaints. The assistant may identify the category and current authorized plan context; a human owns every Continuous Coverage outcome.

## Exceptional support behavior

| Need | Assistant behavior |
| --- | --- |
| Ask what human support can do or request a person | Explain the handoff and transfer the same conversation |
| Check an existing support request | Read only the active user's current ticket state and never invent a response time |
| Add context to the current support conversation | Preserve the user's messages and structured authorized context for the human |
| Complaint, service issue, caregiver concern, discrimination, abuse, neglect, fraud, or safety concern | Transfer without deciding fault or truth; emergency guidance still takes precedence |
| Billing dispute, refund exception, payment investigation, or record correction | Transfer with authorized payment/care context; no financial determination is automated |
| Privacy request, personal-data correction/export/deletion, or suspected account takeover | Transfer without collecting secrets or claiming legal completion |
| Unsupported product behavior | State the limitation truthfully, preserve the user's goal, and transfer when human help is useful |
| Protected information, credentials, tokens, or another household's data | Refuse disclosure or collection and offer safe human help where appropriate |

## No Continuous Coverage mutation tool

`family_administration_v1` contains account/access/notification tools only. There is no Batch 9 tool that creates, edits, assigns, pauses, resumes, cancels, bills, refunds, or closes a Continuous Coverage plan. Catalog records for every `FAM-COVERAGE-*` intent terminate at the Human stage.

That is a deliberate deterministic boundary, not missing implementation. Adding a future Continuous Coverage mutation requires a separate capability, domain service, confirmation and financial contract, and dedicated evaluations.

## Handoff contract

The existing `AiSupportHandoffService` remains the only terminal mutation for Batch 9 exceptional cases. It:

- changes the existing support ticket to human ownership atomically;
- stops automated replies for that ticket;
- preserves the complete transcript;
- records a content-minimized structured reason and authorized resource context;
- alerts the existing Administrator support surfaces;
- avoids queue, wait-time, availability, or outcome promises; and
- does not label the underlying care, payment, safety, privacy, or complaint issue resolved.

The user-facing success claim is **“I transferred this conversation to a human. They'll reply here.”** It proves transfer only. It never means care coverage or the exceptional issue has been completed.

## Governed knowledge and evaluation

The shared `family-administration-support-kb-v1` package contains eight Continuous Coverage entries and eight Support/Privacy/Exceptional entries. It maps all 46 Batch 9 intents and the existing orientation/safety rows that use the same stable truth. Five cases per entry cover positive, boundary, wrong-account, stale/unavailable, and handoff behavior.

Batch 9 contributes **46 intents** and **184 registered phrases**. Together Batches 8 and 9 contribute **118 intents**, **472 registered phrases**, **44 entries**, and **220 evaluation cases**. The generated Family catalog has **324 / 324** explicit KB mappings.

The final source baseline passes **219 / 219 AI Support tests with 5,708 assertions** and the isolated Family Batch 1–9 harness passes **127 / 127 tests with 4,899 assertions**. A focused regression iterates every one of the 26 Continuous Coverage intents, proves the latest authorized plan plus active-shift count is preserved for handoff, and proves plan and shift state remain unchanged.

Use the publication and exact-pilot activation commands in [the Batch 8 record](52-family-administration-communication-records-batch-8.md). Publication and activation never enable **Live for everyone**.

## Source verification contract

Automated tests cover:

- deterministic resolution of all Batch 8/9 registered phrases;
- emergency priority over ordinary Continuous Coverage handling;
- all Continuous Coverage intents transferring with no domain mutation;
- absence of queue-position, wait-time, availability, and resolution promises;
- full-conversation and structured-summary preservation;
- protected-data denial;
- exact-account support-ticket status reads;
- current human-request transfer;
- 44-entry/220-case package structure and idempotent publication;
- all declared Batch 8 tools being default off;
- no Continuous Coverage mutation tool existing; and
- exact-two-pilot-only activation while Everyone remains off.

## Required production audit

After the ordinary deployment, KB publication, and exact-pilot activation:

1. ask for ordinary 24/7 care and verify a same-conversation human transfer;
2. ask an emergency version and verify 911 appears before transfer;
3. verify no answer promises queue status, response time, caregiver availability, or resolution;
4. verify the Administrator receives the transcript and useful authorized summary;
5. verify no care plan, shift, caregiver assignment, payment, or account record changed;
6. return the disposable audit conversation to the intended final state; and
7. confirm **Live for everyone** remains off.

Production deployment and authenticated audit are intentionally not claimed by this source record.
