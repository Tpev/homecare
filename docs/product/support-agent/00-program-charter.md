# Program Charter

Status: Proposed

Last reviewed: August 13, 2026

Owner: Product

Required approvers: Product, engineering, support operations, security/privacy

## Product decision

LoLo will evolve its authenticated human support chat into an intelligent support experience that can:

1. Answer approved product questions in plain language.
2. Understand the signed-in user's current role, page, and authorized care context.
3. Navigate to approved destinations and highlight stable semantic targets.
4. Collect information and prepare validated drafts for bounded workflows.
5. Perform a small set of reversible or controlled actions only after the required confirmation.
6. Transfer the same conversation to a LoLo administrator without making the user repeat themselves.

The intelligent agent is a concierge over LoLo's application, not an independent authority. The application, not the model, decides whether a user may view or change a record.

## Problem

Some LoLo users, particularly older adults and people under care-related stress, struggle with navigation, terminology, multi-step forms, and knowing what to do next. A conventional help center forces them to translate their situation into product language. A general chatbot can sound helpful but may give an inaccurate answer or take the wrong action.

LoLo needs an experience that accepts natural language while preserving the reliability of explicit product workflows.

## Primary users

- Signed-in family account owners and active family members, including users arranging care for themselves or another care receiver
- Signed-in caregivers using LoLo to complete caregiver work and account tasks
- LoLo support administrators receiving or taking over conversations

The program must serve both family/care-receiver and caregiver users. Each role receives a separate capability set, knowledge applicability rules, navigation registry, tool allowlist, authorization boundary, and evaluation corpus. Under `DEC-009`, the shared role-aware foundation is built for both tracks from the start; family/care-receiver capabilities release first, caregiver answers and navigation next, and caregiver operational capabilities individually afterward. Sequencing does not remove caregivers from program scope.

## Product principles

### Truth before fluency

The agent must prefer a short, sourced answer or a human handoff over a polished guess. It never claims success until the application returns an authoritative result.

### The model interprets; LoLo authorizes

The model may map natural language to an intent and proposed arguments. Existing policies, account context, domain services, and validators remain the final authority.

### One clear next step

The user should normally see one question or primary action at a time. The interface may offer **Take me there**, **Show me**, **Help me complete this**, **Change something**, and **Talk to a person** when appropriate.

### Preserve user control

Navigation should be announced. Drafts remain editable. Material writes require a plain-language preview and an explicit confirmation. The user can reach a human at any time.

### Do not make the user repeat context

The agent may use the minimum authorized account and page context needed for the task. A human taking over receives the transcript, current page, collected facts, attempted actions, and failure reason.

### Accessible by construction

The experience must work with large text, keyboard navigation, screen readers, mobile keyboards, reduced motion, and plain-language copy. Accessibility is part of capability acceptance, not a later polish step.

### Capability-by-capability autonomy

LoLo does not release a universal action agent. Each intent independently earns permission to answer, navigate, draft, or execute.

### Invisible until explicitly granted

Deploying the new system must not expose it to live users. During pilot, only an exact user enabled by an authorized administrator may see or invoke AI. Everyone else receives the existing human-support experience with no AI UI and no customer-facing model call.

## Trust contract

LoLo cannot promise that a generative model understands every possible message correctly. LoLo can and must guarantee the following system properties within the supported product boundary:

- No cross-user or cross-family access.
- No material side effect without the declared confirmation.
- No action outside a versioned, approved tool contract.
- No claim of successful action without an authoritative receipt.
- No unsupported product-policy answer presented as fact.
- Complete observable audit events for agent and human activity.
- A visible and functioning human-escalation path.
- A capability-level kill switch.
- No AI visibility or invocation for a non-granted live user during pilot.
- Immediate, audited per-user pilot revocation in the admin UI.
- Only approved, published, applicable KB versions can ground a customer-facing answer.

## Initial supported outcome

The first high-value end-to-end outcome is:

> A signed-in family user can describe a one-time non-medical care need, review a validated draft, explicitly confirm publication, and open the resulting care request. If the process becomes unsafe, ambiguous, unauthorized, or unsuccessful, the same chat transfers to support with the relevant context.

This outcome will be specified and implemented in the new support-agent architecture. The legacy AI care-request copilot described in [the current-state baseline](01-current-state-and-baseline.md) will be removed and does not authorize or supply the new implementation.

## Initial non-goals

- General web browsing or arbitrary computer control
- DOM-coordinate clicking or free-form browser automation
- Medical diagnosis, clinical triage, or medical procedure advice
- Collecting full payment-card details in chat
- Changing payment methods
- Automatically resolving billing disputes, refunds, chargebacks, or time disputes
- Removing family access or transferring account ownership
- Hiring, approving timesheets, capturing payment, or cancelling booked care without a separately approved high-risk capability
- Learning product truth automatically from unreviewed support transcripts
- Hiding whether the user is interacting with automation or a human
- Claiming 24/7 human availability unless operations actually provides it

## Program success measures

Success is not chat volume or automation rate alone. Track:

- Safe task completion by capability
- Grounded answer accuracy
- Wrong-navigation and user-correction rates
- Human handoff quality and time to takeover
- Unassisted completion by older-adult usability participants
- Cross-account and confirmation-control test results
- User trust and satisfaction
- Support minutes saved without increased downstream corrections
- Cost per safely resolved conversation
- Latency and tool reliability

Automation containment must never be optimized at the expense of safety, user comprehension, or correct outcomes.

## Governance

### Product owns

- Supported capabilities and user promises
- Plain-language interaction design
- Capability priorities and release cohorts
- Knowledge-source ownership
- Named pilot membership and KB publication policy

### Engineering owns

- Authorization, tools, transactions, idempotency, and event integrity
- Model integration and fallbacks
- Test harnesses, feature flags, observability, and rollback
- Server-enforced default-off eligibility, per-user grants, and KB lifecycle integrity

### Support operations owns

- Handoff readiness and staffing expectations
- Escalation playbooks
- Transcript review and KB-gap reporting
- Day-to-day KB drafting/review work within granted admin permissions

### Security/privacy owns

- Data minimization, access boundaries, retention, redaction, abuse controls, and incident requirements

### Design/accessibility owns

- Comprehension, interaction patterns, focus behavior, mobile usability, and research with older adults

## Approval boundary

This charter authorizes documentation and evaluation design. It does not authorize production AI replies or new agent side effects. Each capability requires its own approved specification and release evidence.
