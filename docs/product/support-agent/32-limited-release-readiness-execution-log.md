# Limited-Release Readiness Execution Log

Status: Active; limited release remains blocked and customer AI remains disabled

Started: August 14, 2026

Authority: `DEC-067`

## Purpose

This is the chronological handoff record for closing the production evidence gates after the fail-closed readiness layer was deployed. It separates proved facts from planned work so an operator or GPT-5.6 Sol can continue without interpreting a checklist as evidence.

No entry in this log authorizes a grant, enables an AI control, changes either deployment guard, publishes the held pricing entry, or permits a production model conversation.

## Current production boundary

Authenticated production inspection on August 14, 2026 established:

- runtime deployment guard off;
- provider deployment guard off;
- human-only mode on;
- every AI role, capability, commit, and publication control off;
- zero pilot grants;
- 23 governed non-pricing KB entries published and the pricing entry held as Draft;
- no unresolved AI Support incident or warning at the time of inspection;
- Admin readiness `BLOCKED`, with 7 of 21 required checks passing and 14 open;
- public and ordinary Family support remained human-only, with no user-visible AI marker.

## Exact deployed-commit rehearsal

The isolated rehearsal for deployed commit `003c7ccd09249be1fa6c03b731431c7126bb7778` passed its browser, provider-quality, extraction, cost, and temporary-database-destruction gates:

| Metric | Result |
| --- | --- |
| Browser scenario | Pass |
| Frozen provider cases | 56/56 pass; zero hard failures |
| Structured extraction | 27/27 fields, 100% |
| Estimated provider cost | `$0.01787196` |
| Provider P50/P95 | 3,173 / 5,531 ms |
| Temporary database | Verified destroyed |
| Content-free result hash | `885cfc733fafa4aa8e2634a3d17d785c5b2404c998428868071f7d3a988cc421` |

The 5,531 ms P95 remains a warning because it exceeds the five-second target. This run proves the exact commit's isolated release gate; it does not prove production provider configuration, actual alert receipt, rollback staffing, accessibility completion, or older-adult usability.

## Accessibility execution record

### Authenticated production observations

The human support experience was inspected as a Family user before any AI activation:

- at 390 by 844 CSS pixels, the page and open support sheet had no horizontal overflow;
- the minimize control, message field, send control, and Support Center link met the 44-by-44-pixel primary touch-target requirement;
- the support dialog, conversation log, message field, minimize control, send control, and Support Center link exposed meaningful accessible names;
- keyboard focus cycled logically through the enabled composer, Support Center, and minimize controls, skipping the disabled send control and remaining inside the open dialog;
- the support sheet itself reflowed within a 640-by-400 CSS-pixel viewport.

The same 640-pixel inspection found a real failure outside the sheet: the Family desktop navigation activated at the `sm` breakpoint and made the document 829 pixels wide within a 625-pixel client area. Accessibility was therefore not recorded as Passed.

### Remediation and regression coverage

The desktop navigation and account controls now begin at the `md` breakpoint, while the compact navigation remains active below it. The regression test authenticates a Family user, opens human support, and checks the complete document plus panel at 640 by 400, 768 by 480, and 1024 by 640 CSS pixels.

On August 14, 2026, the new test passed in all three configured projects:

- desktop Chromium;
- mobile Chromium emulation;
- mobile WebKit emulation.

The production reflow issue remains open until this remediation is committed, deployed through `deploy.sh`, and rechecked on production. Contrast, an assistive-technology screen-reader run, focus after validation errors, and the complete five-person study also remain open.

## Provider and operations evidence state

The official OpenAI data-control baseline was rechecked on August 14, 2026. API input and output are not used for training unless the organization opts in. Default abuse-monitoring logs may be retained for up to 30 days. Zero Data Retention requires approval and, if approved, can be configured at organization or project level. LoLo's implementation separately sends `store:false` and does not use provider conversations, files, vector stores, background mode, hosted tools, or provider memory.

These documentation facts are not a substitute for inspecting the actual OpenAI project. The following remain unrecorded until the authenticated project and production environment are verified:

- dedicated project and project-scoped credential;
- model-improvement sharing disabled;
- production safety-identifier secret present and separate from all other secrets;
- `$25` monthly project spend alert;
- actual destination/contract position;
- current project retention-control and ZDR-request status;
- both-Administrator email and in-app operations-alert receipt.

## Work queue

1. Deploy and production-retest the navigation reflow remediation.
2. Complete the authenticated OpenAI project inspection and configure the `$25` spend alert.
3. Verify the content-free production environment prerequisites while both deployment guards remain off.
4. Send the content-free operations test and have both Administrators confirm both channels.
5. Record the exact-commit rehearsal, monitoring/cost, provider, and alert evidence in Admin only after each fact is observed.
6. Run the synthetic human-takeover, emergency/24/7, automatic-stop, confirmation-invalidation, rollback, and continuous human-chat drill.
7. Complete the remaining accessibility checks and five qualifying older-adult sessions using the approved study kit.
8. Name exactly two Family pilot users and dates without creating grants.
9. Run the read-only preflight and return for an explicit release decision.

## Explicitly prohibited shortcuts

- Do not mark a planned or code-inspected check Passed.
- Do not simulate the five older-adult participants with team members or an AI.
- Do not infer email delivery from a successful send call.
- Do not create either pilot grant during readiness work.
- Do not turn on the runtime guard, provider guard, master, visibility, role, capability, commit, or publication controls.
- Do not expose credentials, secret fingerprints that enable correlation, customer content, assembled prompts, or model answers in this log or Admin evidence.
