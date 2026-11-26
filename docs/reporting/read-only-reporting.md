# Read-only business reporting: specification and operating plan

## Outcome

Download a complete, verifiable snapshot of the business records to this computer, then answer questions locally without opening admin pages or changing customer records. Typical requests: “What can we close?”, “Who is waiting for a caregiver?”, “What changed this week?”, and “What should customer success confirm?”

The API supplies evidence, not a server-generated assessment. Local reports distinguish recorded CRM status, operational milestones, customer-success statements, and an analyst's interpretation. A lead marked converted is not proof of a completed or paid visit.

## Architecture and boundaries

1. A separate `/api/reporting/v1` route group provides authenticated GET endpoints for `schema` and `export`. Other methods are rejected. It has no mutations, browser session, message read receipts, model saves, queue dispatch, payment-provider calls, or token-last-used updates.
2. A separate database connection automatically clones the application's existing resolved database settings, with a fresh non-persistent PDO and enforced read-only session mode. The application's normal writer connection is untouched. MySQL/MariaDB uses a repeatable-read, read-only transaction over InnoDB tables; PostgreSQL uses a read-only repeatable-read transaction; SQLite uses `query_only` and a read snapshot. No extra database credentials are required.
3. A versioned, explicit column allowlist supplies NDJSON: manifest, records, per-dataset counts and SHA-256 checksums, final completion checksum. Unknown columns never become exports automatically. Missing tables/columns are declared; missing core identity fields fail the export. The client never calls an unavailable dataset “zero.”
4. The Python downloader streams the response into a private local staging directory. It validates the protocol, complete dataset coverage, counts, checksums, and unique IDs, then builds a SQLite index and atomically promotes the directory. Interrupted or malformed downloads never replace the latest good snapshot. HTTPS is mandatory except an explicit local development option; redirects are rejected.
5. Local reports read SQLite, not the live API. They support weekly/current status, traction candidates, caregiver searches, data-quality questions, and a complete evidence packet for a selected lead. Repeated on-demand analysis reuses a snapshot; “refresh” explicitly downloads another full snapshot.

The transaction uses MySQL's documented next-transaction isolation/access settings and establishes the consistent read view with its first InnoDB read. See [transaction characteristics](https://dev.mysql.com/doc/refman/8.4/en/set-transaction.html) and [consistent-snapshot semantics](https://dev.mysql.com/doc/refman/8.4/en/commit.html).

## Exact export contract

The maintained executable field list is `app/Services/Reporting/ReportingCatalog.php`; the generated [field reference](export-fields.md) enumerates every dataset, field, JSON field, and essential field. `GET /schema` reports this same contract against the deployed database. Every included dataset exports all retained rows, including closed, cancelled, unassigned, archived, edited, and replacement records. There is no UI page limit, active-only filter, or date filter.

| Area | Records and purpose |
|---|---|
| Lead identity and acquisition | Leads, external identities, source/campaign attribution, original submission and follow-up dates, ownership, current stage, counters, closure reason, do-not-contact state. Family and referral leads stay distinguishable. |
| Lead history | Every lead activity, its actor, type, summary, full body, occurred/created/updated dates, SMS link, approved business metadata, stage/assignment changes, duplicate-merge provenance, original/survivor business snapshots. |
| Conversations | Full retained inbound/outbound SMS text and delivery state; voice-call summary and transcript; request conversations and every retained message with sender/edit dates; support tickets, public messages, internal notes and activities. Sender and channel remain separate from authorship inferred from prose. |
| Family/onboarding | Users and roles, accounts/members/activity logs, legacy recipient/household care notes, onboarding draft/submitted care information and milestones, welcome visits and staff notes, welcome-email delivery and account/request milestones. Cached payment-setup status and check date, plus payment-setup link lifecycle dates without tokens/URLs. No inference that an absent payment check means payment setup failed or that an old check proves today's provider state. |
| Demand and matching | Requests including staff-managed and generated flags, care needs and schedules, applications and cover notes, invitations/messages, recruitment and reasons, customer-care actions and follow-up notes, shared arrangements and uncovered needs. |
| Delivery | All bookings including cancelled/released replacements, actual and scheduled times, status/terms/disputes/cancellation reasons, event history, change requests, incidents, time corrections; recurring plans, notes, offers and schedule changes; continuous coverage plans, shifts, roster, offers, replacement cases and events. |
| Payment evidence | Booking payment totals/status/timestamps and operations/attempts; care-hour accounts, purchases, lots, allocations, ledger, funding, settlements and operations. Amounts remain integer cents with currency. Buying prepaid hours and redeeming them are separate measures. |
| Caregiver supply | All caregiver users, profile approval/availability/area/rates/review and screening milestones, CRM owner/notes/next action/follow-up, complete CRM activities and moderation notes. Approval, availability, application and actual completed work are separate milestones. |
| Attribution | Marketing daily spend and funnel events, with their original source keys. Missing ingestion or unattributed conversions cannot be treated as zero cost or proven campaign performance. |

Notes are exported in full, without UI truncation. The export preserves separate note and event timestamps and authors. Existing immutable event tables supply history; current-only fields cannot magically reconstruct earlier values. Edited message/note versions and hard-deleted rows that the app no longer retains are not recoverable. Full local snapshots allow later comparisons; disappearance means “no longer present,” not proven deletion or loss. Duplicate-merge metadata supplies aliases without joining people by name.

Explicit exclusions: passwords, session/authentication tokens, payment client secrets, provider payloads, signed/action URLs, payment-method/account identifiers, document files/paths, identity documents, screening evidence, date of birth, precise GPS, and home-entry/access instructions. Useful care needs and objections remain sensitive business data. Free-text URLs and recognizable credentials are redacted; unknown JSON keys are omitted and counted. The nested business-key allowlist is also in the field reference. Redaction is not a promise to detect every secret someone manually typed into prose.

Timestamps remain their stored values. The manifest uses the application's existing timezone for its normal datetime casts and declares UTC-stored exceptions (welcome-visit appointment instants). Date-only values and recurring local schedules are not converted; legacy naive DST times remain ambiguous. Reporting periods are explicit half-open intervals in the selected report timezone, with snapshot time as the upper bound by default.

## Deployment and credential

Ready through normal push/deployment, with no reporting-specific environment settings. The random token has been saved once in the owner's private local reporting directory. Its one-way SHA-256 verifier is prepared in the versioned application config. The downloader reuses the saved token and never puts it on the command line. The API is enabled by default and authenticates this dedicated read-only reporting credential before database access. It has no dependency on browser login, a provisioned administrator ID, or a token table. It remains valid until explicitly rotated or disabled; an optional code-configured expiry is supported but not required.

No customer data or credentials belong in Git. Local snapshots and token files live outside the checkout by default, with restrictive directory/file permissions (Windows ACLs or POSIX modes). HTTPS, `no-store`, no redirects and no content logging protect the transport. Infrastructure access logs may still record endpoint access; they must not log Authorization or bodies. Retention is a deliberate local operator choice; the tool does not silently delete historical evidence.

The existing deployment process loads the prepared verifier and rebuilds Laravel's config cache. No separate activation, database grant, environment edit, or customer-record migration is needed. Disabling the feature or deploying a rotated verifier revokes new requests. The local preparation helper reuses an existing token and only changes the non-secret verifier in the checkout; the private token never enters Git. See the downloader README for report commands.

## KPI definitions

All metrics carry the snapshot time, reporting interval, rule version, denominator, exclusions, and dataset/field availability. Approved local exclusions use exact record IDs plus a reason; test/demo-looking text is a question, never an automatic deletion. Household-level conversions use explicit family-account links; unmatched leads are reported separately, not guessed by email or name. A family can have repeat inquiries without becoming multiple new customers.

| Measure | Definition and limits |
|---|---|
| New demand | Family lead submissions/creation in the period; distinct linked households also shown. Referral/job inquiries are separate; manual misclassification requires confirmation. |
| Response | Time from submission to first call, and separately to first connected contact. No-answer calls do not count as conversations. Flag impossible/negative times and missing first-contact dates. |
| Pipeline | Current family stage counts, active owners, missing/overdue follow-ups and recent inbound signals. Terminal/unreachable/not-fit stages are excluded from active traction candidates. |
| Cohort conversion | For leads acquired in a stated period, households with retained bookings and separately completed visits, as of the snapshot. Report both linked coverage and missing links; current-week bookings divided by current-week leads is not a conversion rate. |
| Request fulfilment | Genuine requests (exclude generated visit requests) by state; pending matching, applicants/shortlists, retained placements, first hire and first delivered visit; stage durations only when recorded timestamps exist. An open request may already have staff-arranged care. |
| Care delivery | Scheduled/started/completed/cancelled bookings and delivered minutes. Released replacement bookings are retained for history and excluded from current commitments. |
| Cash/payment | Captures, refunds, prepaid purchases, prepaid use and balances shown separately by currency. Current accumulated balances cannot determine period cash movement; use dated operations where complete. Do not call gross customer payments net revenue. |
| Caregiver supply | New caregiver accounts, submissions, approvals, accepting-new-client profiles, applications, first completed booking, and active supply by area; profile approval alone does not prove availability. |
| Loss/gain reasons | Analyst reads full chronological notes alongside operations and classifies price, timing, no response, coverage, trust, suitability, competitor, resolved objection, or unknown with evidence and uncertainty. No invented probability of closing. |
| Data quality | Missing IDs/links/owners/follow-ups, converted without delivery, non-converted with delivery, stale dates, absent sources, unexplained losses, duplicate provenance, contradictory or corrected notes. Questions are drafts for the user, never automatically sent to staff. |

## Prebuilt report workflow

The deterministic report creates a compact overview, candidate lists, caregiver-search queue, and CS clarification items with record IDs. It identifies **traction candidates**, not an assured close: recent inbound contact or recent connected/assessment activity, active pipeline stage, and no do-not-contact flag. The skill must read each candidate's evidence packet before describing it as hot, assess objections and corrections in chronological context, and name the remaining closing step.

A caregiver-search queue includes genuine open requests without retained bookings or active recurring placement, explicit arrangement gaps, and unresolved continuous-coverage replacement cases. It distinguishes “no applicants”, “shortlisting”, “offer/placement needs confirmation”, and “replacement gap”; a cancelled/released booking cannot satisfy demand. Existing customers waiting for a replacement remain service issues, not new acquisition wins.

Each opportunity brief should identify customer/account, recorded stage, operational milestone, latest meaningful customer signal and date, CS account of the situation, blocker, owner/next action/date, evidence references, and a confidence/confirmation label. Short status emphasizes decisions and next actions; weekly reports add the same-period metrics and changes since the previous complete snapshot. Snapshot diffs cannot establish the exact time or cause of a change between downloads.

## Delivery and verification

Implementation includes the explicit catalog and read-only API, streaming validation and local index, reproducible report/evidence commands, and installed reporting skill. Tests cover deployment defaults without reporting environment settings, separate connection isolation, disabled/expired/rotated authentication, method restrictions, full note history, redaction, read-only enforcement, checksum/truncation rejection, atomic snapshots, one-time local credentials, and misleading CRM/booking scenarios. Synthetic tests do not claim a production download; normal deployment makes the endpoint available for its first real download.

Incremental exports, scheduled downloads, a server-side reporting replica and advanced attribution are future extensions after measuring full-snapshot size and runtime. Incremental design needs independent event cursors, edits, merges and deletion tombstones before it can replace full snapshots safely.
