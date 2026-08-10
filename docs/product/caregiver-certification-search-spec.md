# LoLo Caregiver Certification Search and Tags Product and Engineering Specification

Status: Proposed

Audience: Product, design, engineering, operations, support, legal/privacy

Primary users: Families searching for, comparing, or inviting caregivers

## 1. Product decision

LoLo will make caregivers' current certifications and training visible as clearly labeled tags and will let families filter caregiver results by certification.

The same certification rules, labels, and filter behavior must be used anywhere a family discovers or invites a caregiver:

- The main **Find caregivers** page
- Suggested matches on a normal or recurring care request
- The caregiver search and invitation flow for a care request
- The caregiver search and invitation flow for Continuous Coverage, including 24/7 care
- Any caregiver card or confirmation step shown as part of those flows

Certification tags must clearly distinguish a credential that LoLo has verified from one reported by the caregiver. LoLo must never imply that a self-reported, pending, expired, or rejected credential is verified.

The feature uses the existing certification taxonomy and caregiver certification records. It does not create a new licensing system or change the existing evidence-review workflow.

## 2. What “everywhere” means

The first release uses the following scope rule:

> If a family can search, browse, receive a suggestion for, or invite a caregiver on a screen, that screen gets certification filters and consistent certification tags. If a screen only displays a caregiver already in a workflow, it gets consistent tags when qualifications help the family make a decision, but it does not get a new filter unless it already contains a list search or filter experience.

| Surface | Certification tags | Certification filters | Notes |
| --- | --- | --- | --- |
| Find caregivers | Yes | Yes | Full filter control; state is reflected in the URL |
| Care-request suggested matches | Yes | Yes | Selected certifications are hard requirements, not ranking hints |
| Care-request search/invite panel | Yes | Yes | Filters apply to previous hires, saved caregivers, recommendations, and text results |
| Continuous Coverage search/invite panel | Yes | Yes | Filters apply to previous hires, saved caregivers, nearby caregivers, and text results |
| Invite confirmation | Yes | No separate control | Repeats the chosen caregiver's matching credentials before sending |
| Applicant comparison or shortlist cards | Yes | Only when that screen already supports filtering applicants | Never searches outside the request's existing applicants |
| Caregiver public profile | Yes, with full detail | No | Existing detailed credentials section remains the source of full context |
| Family dashboard summaries | Only if a caregiver card is shown | No | No new dashboard filter |
| Admin credential review | Existing review state | No family-facing filter | Verification remains an operations workflow |
| Caregiver onboarding/profile editor | Existing edit state | No | Caregivers continue adding and maintaining their own records |

Future caregiver-discovery surfaces must use the shared filter and tag implementation rather than reimplementing the rules.

## 3. Product goals

1. A non-technical family member can find caregivers with a relevant certification in a few taps.
2. A family can tell at a glance whether a credential was verified by LoLo or reported by the caregiver.
3. The same caregiver produces the same credential labels on every search and invitation surface.
4. Selecting more than one certification has a simple, predictable meaning.
5. Existing search results, eligibility rules, and ranking do not change when no certification filter is selected.
6. Expired and rejected credentials never satisfy a search filter.
7. Private credential evidence is never exposed to families, other caregivers, URLs, analytics, logs, or Livewire state.
8. The feature can be deployed additively without requiring existing caregivers or families to take action.

## 4. Non-goals for the first release

- Government or third-party license-registry integration
- Automated credential verification
- Claiming that LoLo verification guarantees licensure, competence, suitability, or scope of practice
- Filtering caregivers by the uploaded document, issuer, credential number, reviewer, or exact expiration date
- Free-form creation of family-facing filter categories from a caregiver's custom text
- Boolean filter builders such as AND/OR groups
- Certification-based pricing or automatic hiring decisions
- Automatically changing the normal ranking when the family has not selected a certification
- Showing expired or rejected credentials on search cards
- Replacing skills, care experience, identity, background-check, availability, distance, rate, language, or reliability filters
- Changing caregiver marketplace eligibility or invitation authorization rules

## 5. Existing data and terminology

The application already stores certification types and caregiver certification records. Existing public statuses are:

- `verified`
- `self_reported`
- `pending`
- `rejected`

The current seeded taxonomy is:

- CPR
- First Aid
- Basic Life Support (BLS)
- Certified Nursing Assistant (CNA)
- Home Health Aide (HHA)
- Personal Care Aide (PCA)
- Medication Aide or Technician
- Dementia care training
- Other certification or training

Only active certification types may appear as filters. The database remains the source of truth; labels must not be hard-coded separately in each Livewire component.

## 6. User-facing language

Use the filter title:

> **Certifications & training**

Use the helper text:

> Choose what the caregiver must currently have. Select more than one to require all of them.

Use these public status labels:

| Stored state | Current? | Card/profile label | Can satisfy “Any current credential”? | Can satisfy “LoLo verified only”? |
| --- | --- | --- | --- | --- |
| Verified | Yes | **LoLo verified** | Yes | Yes |
| Self-reported | Yes | **Reported by caregiver** | Yes | No |
| Pending review | Yes | **Reported by caregiver** | Yes | No |
| Verified, but expired | No | **Expired** on full profile only | No | No |
| Self-reported/pending, but expired | No | **Expired** on full profile only | No | No |
| Rejected | Any | Not shown publicly | No | No |

Do not use only the word **Verified** without identifying LoLo as the verifier. Do not call a self-reported credential **unverified**, which can sound accusatory; use **Reported by caregiver**.

Where longer explanatory copy is appropriate, use:

> LoLo verified means our team reviewed the information submitted by the caregiver. It is not a guarantee of current licensure, suitability, or quality. Confirm any credential required for your care.

The compact card tag must include visible text or an accessible label; green color or an icon alone is insufficient.

## 7. Family experience

### 7.1 Default state

The certification control is collapsed or visually compact by default and shows:

> Certifications & training — Any

No certification type is selected and the verification mode is **Any current credential**. In this state:

- No certification constraint is added to the query.
- Existing results and sort order remain unchanged.
- Caregivers without certifications remain eligible.
- Current certification tags may still be displayed on their cards.

### 7.2 Choosing certifications

Opening the control shows a checkbox list of active certification types in configured `sort_order`. A family can select one or more.

Examples:

- Selecting **CPR** returns caregivers with a current CPR record.
- Selecting **CPR** and **First Aid** returns caregivers with both current records.
- Selecting **Other certification or training** matches the existing `other` certification type; it does not generate a filter for every custom credential name.

Selected items appear as removable filter chips above the results. The summary changes to, for example:

> Certifications & training — CPR + 1

The user has both:

- **Clear certifications**, which clears only certification selections and verification mode.
- The screen's existing **Clear all filters**, which clears certifications along with other filters.

Changing or clearing a certification filter resets pagination to page one and refreshes the result status announcement.

### 7.3 Verification choice

When at least one certification is selected, show a two-choice control:

- **Any current credential** — includes LoLo-verified and caregiver-reported credentials
- **LoLo verified only** — includes only current credentials LoLo has verified

The default is **Any current credential**. This produces useful results for families while keeping the evidence state explicit on every result.

If **LoLo verified only** returns no results, the empty state may offer a one-click action:

> Include credentials reported by caregivers

That action changes the verification mode; it must not silently broaden the results.

### 7.4 Search text

On screens with an existing text search, the search may also match standardized certification labels and common abbreviations, including CPR, BLS, CNA, HHA, and PCA.

Text search does not activate the structured certification filter. For example, typing `CNA` can find caregivers who list CNA, but the results must still display whether each matching credential is LoLo verified or caregiver reported.

Custom names entered under **Other certification or training** may be matched by text search if they are already public on the caregiver's profile. They must never become new global checkbox options.

### 7.5 Empty and partial states

Empty-state copy names the active requirement in plain language:

> No caregivers match CPR and CNA with LoLo verification right now.

Actions:

- Remove **LoLo verified only**
- Clear certifications
- Clear all filters

The interface must not suggest that no caregiver is qualified in general; it only states that none matched the chosen filters.

## 8. Filter semantics

### 8.1 Multiple selections use AND

All selected certification types are required.

If a family selects CPR and CNA, a caregiver must have a current qualifying CPR record and a current qualifying CNA record. A caregiver with only one does not match.

AND is the only mode in the first release. The helper copy states this behavior; no AND/OR switch is shown.

### 8.2 Current credential

A credential is current when:

- Its status is `verified`, `self_reported`, or `pending`; and
- `expires_at` is null or is today or later in the application's configured timezone.

A credential is not current when:

- Its status is `rejected`; or
- Its `expires_at` date is before today.

An expiration date of today remains current through the end of today in the application timezone.

### 8.3 Verification modes

For **Any current credential**, qualifying statuses are:

- `verified`
- `self_reported`
- `pending`

For **LoLo verified only**, all of the following are required:

- `verification_status = verified`
- The credential is current
- The existing verification record is internally valid according to the certification model's verified-state invariant

Do not infer verification from the presence of a document.

### 8.4 Eligibility and ranking

Certification filtering is applied after the surface's existing authorization and marketplace-eligibility constraints. It must never allow a caregiver who is otherwise unavailable, outside the permitted marketplace, blocked, inactive, or ineligible.

When filters are selected, certifications are hard eligibility requirements. They are not merely score bonuses.

When filters are not selected:

- Existing suggestion scores remain unchanged.
- Existing ordering remains unchanged.
- A caregiver is neither rewarded nor penalized for having a certification.

Certification-based ranking can be evaluated separately after there is evidence that families need it.

## 9. Certification tag behavior

### 9.1 Separate credentials from experience tags

Search cards currently combine care experience and certifications under a shared tag limit. This can hide a caregiver's certification behind experience tags.

The new implementation must treat them as separate groups:

- **Certifications & training**
- **Care experience**

A shared presenter or Blade component renders certification tags consistently on all listed surfaces.

### 9.2 Ordering

Certification tags are ordered as follows:

1. Certification types selected in the active filter, in filter display order
2. Other current LoLo-verified certifications
3. Other current caregiver-reported certifications
4. Certification type `sort_order`, then label

Expired and rejected credentials are omitted from cards.

### 9.3 Card limits

When no certification filter is selected, show up to three certification tags followed by **+N more** when needed.

When certification filters are selected, every selected matching certification must be visible on the result card, even if that temporarily exceeds the normal three-tag limit. Remaining credentials may be collapsed under **+N more**.

Each tag contains:

- The certification label
- A visible state, **LoLo verified** or **Reported by caregiver**
- An accessible label containing both the certification and state

Example:

> CPR · LoLo verified

### 9.4 Invite confirmation

Before a family sends an invitation, the confirmation state repeats the selected caregiver's matching certification tags. This protects against choosing the wrong card in a long result list and keeps the verification state visible at the decision point.

The invitation record does not need to snapshot a credential in the first release. If the credential changes later, the caregiver's current profile is authoritative. Existing notification and invitation copy must not claim that a credential is guaranteed for the future visit.

## 10. Surface-specific behavior

### 10.1 Find caregivers

- Add **Certifications & training** beside the existing skills, languages, trust, rate, ZIP, and sort controls.
- Persist selected certification type slugs or IDs and verification mode in the URL using the same Livewire URL-state pattern as existing filters.
- Use stable, non-sensitive values in the URL; never include custom names, document identifiers, issuer details, or expiration dates.
- Browser back/forward and copied URLs restore the filters.
- Invalid or inactive type values are ignored and removed from the normalized state.

### 10.2 Care-request suggested matches

- The suggestions area exposes the same certification control or inherits the active control in its surrounding request discovery UI.
- `CaregiverSuggestionService` accepts the normalized certification criteria.
- The criteria are applied before scoring and limiting results.
- Recommendation reasons may say **Has current CPR** or **LoLo-verified CPR** only when supported by the current record.
- Without certification criteria, the existing suggestion algorithm and scores are unchanged.

### 10.3 Care-request invitation discovery

- The invite panel includes the same filter beneath the caregiver name/city search.
- Active filters apply to every initial section: previously hired, saved, and recommended.
- Active filters also apply to text-search results.
- Section counts and visibility reflect the filtered results.
- A caregiver selected before a filter change is cleared if they no longer match.
- Closing the modal clears transient text and certification filters, matching the existing fresh-search behavior. Reopening starts at **Any**.

### 10.4 Continuous Coverage invitation discovery

- Use the same control, query semantics, tag component, and empty-state language as the care-request invite panel.
- Active filters apply to previous hires, saved caregivers, nearby/browse sections, and text-search results.
- Changing filters must not alter role, eligible-day, shift-type, or replacement-preference choices unless the selected caregiver becomes ineligible.
- A selected caregiver who no longer matches is cleared with a polite status message.

### 10.5 Applicant and shortlist cards

- Display current certification tags with the same verification language.
- If the screen already offers applicant filters, the certification filter operates only on the current request's applicants.
- Do not introduce a global caregiver search into applicant comparison.
- A credential changing after application updates the displayed current credential state; it does not delete or modify the application.

### 10.6 Public caregiver profile

- Keep the full credential list, issuer where already intended to be public, and current/expired state.
- Use the same public status presenter as cards.
- Rejected credentials and private evidence remain hidden.
- Filter tags link to or focus the corresponding detailed credential only if the current profile design supports that without adding navigation complexity.

## 11. Shared application architecture

### 11.1 Normalized filter object

Create one shared criteria object, for example `CaregiverCertificationCriteria`, containing:

- `typeIds`: unique active certification type IDs
- `verification`: `any_current` or `verified_only`

It is responsible for normalizing URL/Livewire input and rejecting unsupported values. Livewire components do not construct ad hoc status rules.

### 11.2 Shared query implementation

Create one reusable query scope or service that applies the criteria to a `CaregiverProfile` query.

Because multiple selections use AND, add one correlated `whereHas` constraint per selected type, or use an equivalent grouped query that proves the caregiver has every selected type. A single `whereIn` is insufficient because it implements OR.

Conceptual behavior:

```php
foreach ($criteria->typeIds as $typeId) {
    $query->whereHas('certifications', function ($certifications) use ($typeId, $criteria) {
        $certifications
            ->where('caregiver_certification_type_id', $typeId)
            ->current()
            ->when($criteria->requiresVerification(), fn ($query) => $query->verified());
    });
}
```

The exact API may differ, but every discovery service must call the same implementation.

### 11.3 Shared public presenter

Extract certification presentation from the existing mixed `publicCareBackgroundTags()` behavior into a shared method or presenter that returns only safe public data:

- Type ID or slug
- Public label
- Public verification label
- `verified` boolean for styling
- `matches_filter` boolean when criteria are supplied
- Sort position

It must not return:

- Document path, disk, filename, URL, or storage key
- Credential/evidence number
- Internal verification note
- Reviewer identity
- Rejection reason
- Raw uploaded metadata

Care experience tags can continue using their own presenter.

### 11.4 Reusable UI components

Prefer shared components for:

- Certification filter fieldset
- Active certification filter chips
- Certification tag group
- Empty-state filter summary

Surface-specific components may control layout, but they must not define their own labels, status mapping, or expiration logic.

### 11.5 Livewire state

Recommended public properties:

```php
public array $certificationTypes = [];
public string $certificationVerification = 'any_current';
```

Validate and normalize both server-side on mount, hydration, update, and action submission. Do not trust array values only because they originated in a checkbox.

Main browse persists normalized state in the URL. Modal flows keep state only for the open modal session and reset it on close.

## 12. Data and indexing

No new domain table is required.

Add a forward-only migration with an index suitable for filtering current credentials by type and verification state, after confirming the generated queries with `EXPLAIN`. A likely index is:

```text
(caregiver_certification_type_id, verification_status, expires_at, caregiver_profile_id)
```

Retain the existing unique caregiver-profile/type constraint and current indexes. The migration must not rewrite credential records or require downtime.

The implementation must eager-load the minimal certification/type columns needed for card tags and must not introduce N+1 queries.

If result caching exists, cache keys include normalized type IDs, verification mode, and the current date. Credential updates, review decisions, and expiration boundaries must not leave stale search eligibility.

## 13. Security, privacy, and trust requirements

1. Apply existing family authorization and marketplace-eligibility scopes before certification filtering.
2. Treat all Livewire and URL filter values as untrusted input.
3. Only active, known certification types may affect a query.
4. Never serialize credential evidence or internal review data into family-facing HTML or Livewire payloads.
5. Do not create signed or public document URLs for search results.
6. Do not log custom credential text, issuer details, evidence filenames, internal notes, or rejection reasons as filter analytics.
7. Use current server-side status and expiration data at query time; never rely on a tag sent back by the browser.
8. Keep the wording **LoLo verified** limited to records in the verified state and still current.
9. Verification does not bypass background, identity, availability, service-area, account-status, blocking, or request-specific eligibility rules.
10. Families cannot filter for rejected or expired records and cannot infer that such records exist.

## 14. Accessibility and simple-use requirements

- Use `fieldset` and `legend` for certification type choices and verification mode.
- Every checkbox and radio control has a visible label and at least a 44-by-44-pixel touch target.
- The control is fully usable by keyboard.
- Focus is visible and returns to the filter trigger when a mobile sheet or popover closes.
- Filter chips have descriptive remove labels such as **Remove CPR filter**.
- Result counts and selection-cleared messages use a polite live region.
- Status is communicated with text, not color or icon alone.
- On mobile, use a bottom sheet or stacked panel; do not require a precision multi-select dropdown.
- Keep active requirements visible after the control closes.
- Do not expose internal language such as `pending`, `self_reported`, query operators, or type IDs.

## 15. Error and edge-case behavior

| Scenario | Required behavior |
| --- | --- |
| Certification expires today | It matches through the end of today in the app timezone |
| Certification expired yesterday | It does not match and is omitted from cards |
| Verified credential becomes rejected | It immediately stops matching and disappears publicly |
| Self-reported credential enters pending review | It remains **Reported by caregiver** and still matches `any_current` |
| Type becomes inactive | It disappears from controls; stale URL/state values are ignored |
| Filter value is forged or malformed | Ignore it safely; do not error or broaden authorization |
| Caregiver has `Other` with custom text | Match the `Other` filter and display the safe public custom label where supported |
| Selected caregiver no longer matches after refresh | Clear the selection and explain that the caregiver no longer matches the filters |
| No selected filters | Preserve existing results, ranking, eligibility, and pagination behavior |
| More than three matching selected credentials | Show all selected matches; collapse only additional credentials |
| Duplicate values arrive from URL/Livewire | Normalize to unique active types |
| Credential changes while an invite modal is open | Revalidate before confirmation and again before sending |
| Certification service/query fails | Fail closed for the selected filter and show a retryable error; do not return unfiltered results silently |

## 16. Analytics and operational visibility

Track only what is necessary to understand adoption and failures:

- Certification filter opened
- Number of certification types selected
- `verified_only` selected or removed
- Results count bucket
- Empty-state recovery action used
- Search or invitation query error

Do not include custom credential names, issuer details, document metadata, reviewer information, expiration dates, or rejection information in analytics events.

Standardized certification type IDs or slugs may be added to product analytics only after privacy review. They are not required for the first release.

Operational logs should identify the surface and request trace, not private certification evidence.

## 17. One-deployment release plan

This is an additive feature and can ship in one production deployment if the migration and application changes remain backward compatible.

Recommended deployment order within the release:

1. Run the additive index migration.
2. Deploy shared criteria, query, and presenter code.
3. Deploy the reusable controls and tag components to all in-scope surfaces in the same artifact.
4. Clear relevant application/view caches.
5. Run production smoke checks with a current verified credential, a current reported credential, an expired credential, and a caregiver without credentials.

No data backfill is required. If rollback is necessary, the old application can ignore the additive index. Do not remove existing columns or reinterpret stored statuses in this release.

Before deployment, record baseline result IDs for representative searches. After deployment, verify that those IDs and ordering are unchanged when no certification filter is active.

## 18. Acceptance criteria

### Consistency

- Every in-scope caregiver search and invite surface uses the same active certification options.
- The same credential has the same label and verification state on every family-facing card.
- Search cards no longer allow care-experience tags to hide all certification tags.
- Invite confirmation repeats the selected caregiver's matching credentials.

### Filtering

- Selecting CPR returns only caregivers with a current CPR record.
- Selecting CPR and CNA returns only caregivers with both current records.
- **Any current credential** includes verified, self-reported, and pending current records.
- **LoLo verified only** includes only current verified records.
- Expired and rejected records never satisfy a filter.
- Inactive or forged certification types do not affect results.
- Clearing certification filters restores the unfiltered result set.
- With no certification filter, existing eligibility, ranking, and order are unchanged.

### Tags and trust

- A current verified record displays **LoLo verified**.
- A current self-reported or pending record displays **Reported by caregiver**.
- Expired and rejected records are omitted from search and invitation cards.
- Selected matching tags remain visible even when the caregiver has more than three credentials.
- No private document or internal review data appears in HTML, Livewire payloads, URLs, browser logs, or analytics.

### Surface behavior

- Main browse filters survive refresh and browser back/forward through normalized URL state.
- Care-request invite filters apply to initial sections and text results.
- Suggested-match filters are applied before scoring and result limits.
- Continuous Coverage filters apply to initial sections and text results.
- A selected caregiver is cleared if a filter change makes them ineligible.
- Empty states name the selected requirements and provide an explicit way to broaden or clear them.

### Accessibility and performance

- Certification controls are keyboard- and screen-reader-usable on desktop and mobile.
- Result updates are announced without moving focus unexpectedly.
- Search cards do not add N+1 queries.
- Representative filtered queries meet the application's existing caregiver-search response target with production-like data.

## 19. Required automated coverage

### Model and criteria tests

- Current versus expired date boundaries in the app timezone
- Verified, self-reported, pending, and rejected status mapping
- Criteria normalization, de-duplication, inactive types, and forged input
- Safe public presenter output excludes every private evidence field
- Selected-tag and verification-first ordering

### Query tests

- One selected certification
- Multiple selected certifications use AND, not OR
- `any_current` versus `verified_only`
- Null expiration and date-boundary behavior
- Other certification type behavior
- Existing eligibility scopes remain enforced
- No-filter result IDs and ordering remain unchanged

### Feature tests

- Find caregivers URL state, pagination reset, clear-one, and clear-all
- Care-request initial invite sections and text results
- Suggested matches filter before score/limit
- Continuous Coverage initial sections and text results
- Invite selection invalidation and confirmation tags
- Applicant cards use the same labels
- Empty-state recovery action
- Public profile/card status consistency

### Security tests

- Cross-account and unauthorized request behavior remains denied
- Malformed Livewire state cannot bypass eligibility
- Document path, disk, filename, internal notes, reviewer, and rejection reason are absent from responses and Livewire snapshots
- Revalidation occurs before an invitation is sent

### Accessibility and performance checks

- Automated accessibility checks for filter controls, chips, live regions, and modal focus
- Mobile viewport interaction test
- Query-count assertion for result lists
- `EXPLAIN` review for the main browse and both invitation discovery queries

## 20. Implementation checklist

1. Add shared certification criteria normalization.
2. Add shared current/verified query scopes or filtering service.
3. Add the supporting index migration after query-plan review.
4. Separate certification tags from mixed care-experience tags.
5. Add shared certification tag and filter UI components.
6. Integrate main caregiver browse with URL state.
7. Integrate care-request suggested matches.
8. Integrate care-request invitation discovery, including initial sections.
9. Integrate Continuous Coverage invitation discovery, including initial sections.
10. Add tags to decision cards and invitation confirmation.
11. Add security, feature, accessibility, query-count, and regression tests.
12. Run production-like smoke checks for verified, reported, expired, rejected, and no-credential profiles.

## 21. Final product boundary

This feature helps a family ask:

> Which available caregivers currently report the certifications or training important for this care, and which of those records has LoLo reviewed?

It does not answer:

> Is this caregiver legally or clinically qualified for every task in my situation?

That distinction must remain clear in the interface, implementation, support material, and analytics.
