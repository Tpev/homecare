# CareLoLo — Google Analytics audit

Reviewed 13 September 2026 through the open, signed-in Google Analytics browser tab. Account: **LoLoCare**. GA4 property: **CareLoLo (549402890)**. Website: **carelolo.com**.

## Assessment

**Recorded activity has grown substantially, but GA4 cannot yet establish whether customer acquisition, qualified leads, bookings, or revenue are improving.** The property mixes public marketing visits, family and caregiver product usage, staff activity, and testing. Business outcome events are defined but receive no data. Attribution also credits payment and verification return journeys as acquisition sources.

The owner subsequently confirmed that **all France traffic is their own activity**. In the reviewed window, France represents **257 reported active users (31.38%) and 5,353 events (33.57%)**. Treat this as confirmed internal activity, not customer acquisition. These user counts do not mean 257 different people; one person's activity is represented across many Analytics identities. The reason for that fragmentation was not established.

The most urgent work is to prevent sensitive URL collection, implement meaningful outcome events, and separate staff/testing from customer activity. Fix attribution before using these reports to allocate marketing spend.

This was a read-only audit. No website code, tag configuration, account links, filters, or conversion definitions were changed.

## Period and evidence

Primary window: **15 August–11 September 2026**, compared with **18 July–14 August 2026**, two consecutive 28-day periods. The property reports in **New York time** and **USD**. The latest day shown in the original default report was omitted to reduce recent-data effects; processing can still revise recent figures.

Reviewed: user and traffic acquisition, all 29 source/medium rows, campaigns, all nine recorded event names, all 412 page-path rows, all 124 landing-page rows, Google organic landing pages, country/region/city segments, browsers and devices, lead acquisition, acquisition cohorts, engagement/retention overview, and core administration settings. Detailed geography tables were reviewed through their leading rows, not every geographic combination.

Reports inspected displayed **100% of available data**. That does not establish tracking completeness or remove contamination. User metrics differ by scope: total users are 820; active users are 819. People can occur in multiple device or dimension rows. GA4 estimates and aggregation can also produce small differences: landing-page row sessions summed to 1,730 while the report total was 1,729. Headline totals below use the report totals.

## 1. Performance scorecard

| Metric | Current 28 days | Previous 28 days | Change |
|---|---:|---:|---:|
| Total users | 820 | 380 | +115.8% |
| New users | 808 | 384 | +110.4% |
| Returning users | 78 | 26 | +200.0% |
| Sessions | 1,729 | 484 | +257.2% |
| Engaged sessions | 1,116 | 355 | +214.4% |
| Engagement rate | 64.55% | 73.35% | **−8.80 percentage points** |
| Average engagement per session | 2m 27s | 2m 11s | +12.5% as reported |
| Average engagement per active user | 5m 11s | 2m 47s | +86.4% as reported |
| Events | 15,946 | 4,079 | +290.9% |
| Key events | **0** | **0** | No measured outcomes |
| Recorded revenue | **$0** | **$0** | No measured revenue |

There were **8,048 page views** in the current window. This scorecard retains the original, unfiltered property totals, including the owner's France activity. The daily chart showed a pronounced mid-August spike followed by much lower activity; the period increase should not be described as steady growth. The audit did not establish whether deployment or tracking changes contributed to the increase from the small prior baseline. External-audience growth requires excluding France from both comparison periods and separating any remaining staff/testing activity; it cannot be calculated by subtracting France from the current period alone.

The engagement rate fell despite longer average engagement. More time in administration, billing, or onboarding can raise engagement without producing more customers. Neither $0 nor zero key events proves there were no real sales or leads: it means GA4 recorded none.

## 2. Acquisition channels

| Session channel | Sessions | Share | Previous sessions | Engagement rate | Avg. engagement/session |
|---|---:|---:|---:|---:|---:|
| Direct | 1,078 | 62.35% | 429 | 60.67% | 1m 43s |
| Organic Search | 331 | 19.14% | 27 | 72.81% | 4m 13s |
| Referral | 185 | 10.70% | 28 | 71.89% | 3m 37s |
| Organic Shopping | 89 | 5.15% | 0 | 76.40% | 3m 15s |
| Organic Social | 23 | 1.33% | 1 | 34.78% | 8s |
| AI Assistant | 11 | 0.64% | 0 | 63.64% | 2m 07s |
| Unassigned | 6 | 0.35% | 0 | 0% | 16s |
| Paid Social | 4 | 0.23% | 0 | 75.00% | 1m 07s |

All channels recorded zero key events and revenue.

**Direct dominates both sessions and first acquisition.** Of 808 new users, 668 (82.67%) were first attributed to Direct; 56 to Organic Search, 51 Referral, 19 Organic Social, 10 AI Assistant, and four Paid Social. Direct is traffic without a clear referral source; it can include untagged marketing links as well as bookmarks and typed URLs. It is not proof of brand demand. Standardize campaign tags on outreach and paid links. [Google: Direct traffic](https://support.google.com/analytics/answer/15258820?hl=en), [campaign URL tagging](https://support.google.com/analytics/answer/10917952).

**Organic search is growing, but its session count includes operational usage.** Organic Search sessions increased from 27 to 331, while new users first attributed to Organic Search increased from 13 to 56. Those are different measurement scopes. Long engagement and repeated app usage must not be presented as hundreds of newly acquired prospects.

**Organic Shopping is entirely Stripe returns.** Adding session source/medium to the channel report confirmed all 89 sessions: checkout.stripe.com/referral (46) and connect.stripe.com/referral (43). This channel is not evidence of product discovery through shopping results.

### Source detail and implications

| Source / medium | Sessions | Engagement | Interpretation |
|---|---:|---:|---|
| google / organic | 323 | 73.37% | Largest identifiable search source; includes app/staff activity |
| verify.didit.me / referral | 63 | 69.84% | Verification return journey; attribution cleanup needed |
| craigslist.org / referral | 55 | 76.36% | Promising identifiable acquisition source; distinguish caregiver supply from family demand |
| checkout.stripe.com / referral | 46 | 86.96% | Payment return journey |
| connect.stripe.com / referral | 43 | 65.12% | Payout/account setup return journey |
| assets.parceldns.com / referral | 20 | 75.00% | Identify the referring workflow before classifying or excluding |
| hub.healthcare / referral | 19 | 84.21% | Relevant source to evaluate once leads are measured |
| m.facebook.com / referral | 13 | 23.08% | 3s average engagement/session; weak observed engagement, small sample |
| chatgpt.com / ai-assistant | 10 | 70.00% | Small measurable source, not enough for business-impact claims |
| cmsne.org / referral | 9 | 66.67% | 6m 12s average engagement; small sample |
| bing / organic | 6 | 50.00% | Small search contribution |
| facebook.com / referral | 6 | 50.00% | 3s average engagement/session |
| upwork.com / referral | 5 | 60.00% | May represent recruiting/contractor activity; verify purpose |
| fb / paid | 4 | 75.00% | Only four recorded paid-social sessions |

Didit and the two Stripe sources together account for **152 sessions, 8.79% of reported sessions**. These are attributed workflow returns, not 152 additional acquired customers. They should not simply be subtracted from user or session totals.

The campaign report showed only four sessions for a numeric Meta campaign identifier. GA4 has no Google Ads link; the Meta page offers connection/import setup rather than showing imported campaign performance. **CPA, ROAS, ad-spend efficiency, and paid campaign success cannot be assessed from this property as reviewed.** Four attributed sessions do not prove ads failed or did not run.

## 3. Landing pages and content

| Landing page | Sessions | Share | Active users | Average engagement/session |
|---|---:|---:|---:|---:|
| /login | 615 | 35.57% | 451 | 1m 32s |
| / | 431 | 24.93% | 195 | 2m 39s |
| (not set) | 94 | 5.44% | 36 | 1m 49s |
| /caregivers | 83 | 4.80% | 74 | 2m 53s |
| /dashboard | 40 | 2.31% | 18 | 4m 47s |
| /legal/platform-terms-of-service | 28 | 1.62% | 28 | 9s |
| /sdr/calling | 27 | 1.56% | 3 | 13m 10s |
| /register | 26 | 1.50% | 24 | 18s |
| /forgot-password | 25 | 1.45% | 25 | 15s |

Login is the largest entry point and accounts for 426 reported new users. Investigate acquisition links, authentication redirects, cookie continuity, and automated/testing visits. A new browser identifier arriving at login is not necessarily a newly acquired customer. The 94 unset landings warrant a page-view/session sequencing check; the report alone does not identify the cause.

For **google / organic**, the leading entry pages were homepage (125 sessions), login (50), unset (34), staff calling (26), dashboard (10), and staff family-calling (8). Together the two staff calling routes account for 34 Google-organic-attributed sessions. This does not establish that those private routes are indexed: GA4 attribution and application navigation can associate an operational landing page with an earlier acquisition source.

Blog paths received **54 page views** and approximately **31 landing-page sessions** across all sources. Only six landing sessions were attributed to google/organic across five blog paths. Content currently contributes a small share of measured entry traffic. Grow service and editorial traffic based on the separate Search Console audit, while measuring whether visitors then make a qualified request. Do not compare GA4 sessions directly with Search Console clicks as interchangeable metrics.

### Page-view composition

Mutually exclusive path-prefix groups from all 412 page rows:

| Page group | Views | Share of 8,048 |
|---|---:|---:|
| Family app paths | 1,475 | 18.33% |
| Caregiver app/onboarding paths | 1,322 | 16.43% |
| Admin paths | **1,288** | **16.00%** |
| Authentication paths | 1,133 | 14.08% |
| Dashboard | 676 | 8.40% |
| Blog | 54 | 0.67% |
| Other paths | 2,100 | 26.09% |

“Other” includes public pages and additional operational routes such as staff calling, messages, and care requests. These groups do not identify individual people, and customer app/onboarding use is valid product activity. The problem is combining all of it into a marketing performance total.

Examples show the concentration: admin CRM received 304 views from 42 active users, admin users 252 views from nine, family requests 320 views from 23, and family request creation 142 views from 15. Generic page title **“LoLo”** groups about 5.8k views together. Use sanitized route groups and meaningful titles to make reports interpretable.

## 4. Events, leads, payments, and retention

Only these nine event names had activity in the reporting window:

| Event | Count |
|---|---:|
| page_view | 8,048 |
| user_engagement | 1,907 |
| session_start | 1,729 |
| scroll | 1,705 |
| form_start | 884 |
| first_visit | 808 |
| click | 713 |
| form_submit | 145 |
| view_search_results | 7 |

The administrator event list contains **close_convert_lead, purchase, and qualify_lead**, each showing no stream data in the last 28 days. The lead acquisition report shows zero new, qualified, and converted leads. Acquisition cohorts show zero transactions and zero measured value. There is no active recorded sign-up, booking, lead-generation, or payment-success event in the inspected period.

**The 145 form submissions are not 145 leads.** The page-path breakdown was:

| Location of form_submit | Count |
|---|---:|
| Caregiver payout connection | 45 |
| Family billing | 32 |
| Caregiver verification | 27 |
| Caregiver invitations | 16 |
| Caregiver work inbox | 10 |
| Admin paths, combined | 6 |
| Homepage | 3 |
| OAuth authorization | 3 |
| Family request creation | 1 |
| Caregiver setup | 1 |
| Caregiver onboarding | 1 |
| **Total** | **145** |

There were 63 users associated with form_submit, and forms can be repeated. The 884 starts and 145 submits do not form a valid customer-conversion funnel because they combine unrelated forms and repeat actions. Marking every automatic form_submit as a key event would reward billing, verification, and administration activity. Define successful business actions narrowly. [Google: measuring key events](https://support.google.com/analytics/answer/12946393?hl=en).

The click event is unusually concentrated: **713 events from six reported users**. Inspect the actual link destinations and repeat-firing behavior in a controlled test. The aggregate report does not prove duplication, bots, or a defect.

Returning users rose to 78 from 26. Retention charts were sparse and variable, with many cohorts near zero. This is not a validated customer retention measure: staff and caregiver use is mixed in, cohorts are small, and recent cohorts have not matured. Do not label 78/820 as D7 or D30 retention. Establish separate family and caregiver cohorts based on activation and subsequent meaningful actions.

## 5. Geography and technology

The US accounts for **491 active users (59.95%)** and France for **257 (31.38%)**. **The owner confirmed all France traffic is their own**, so France and its Grand Est/Barr activity must be excluded when assessing customer demand in this audit. Grand Est accounts for 255 active users. France also accounts for **5,353 of 15,946 events (33.57%)**, leaving **10,593 events outside France**. Those remaining events can still include staff, tests, providers, and existing customers. Country-level user counts are not additive unique-person counts: do not label 819 minus 257 as a verified number of external people. France traffic and admin-route activity can overlap, so their percentages must not be added together.

North Carolina has **95 active users (11.60% of the unfiltered total)**, 387 engaged sessions, and 5,103 events (32.00% of events). Raleigh has 43 active users and 2,582 events; Charlotte 12 and 694; Durham 12 and 252. These local users are active, but other staff activity and missing outcome events prevent interpreting them as local customers.

Barr has 104 active users, San Jose 62, Des Moines 49, and Moses Lake 44. City is unset for 301 active users (36.75%). The France classification is based on the owner's confirmation, not an inference from location. Outside France, admin usage and visible demo/audit fixture paths justify investigating additional staff/testing contamination. Other geographic patterns alone cannot establish that visitors are staff or bots; network geolocation is approximate. Do not extend the France finding to all non-local traffic.

| Device | Active users | Engagement rate | Avg. engagement/active user |
|---|---:|---:|---:|
| Desktop | 621 | 62.68% | 4m 50s |
| Mobile | 196 | 66.67% | 5m 36s |
| Tablet | 5 | 89.29% | 29m 45s |

Desktop represents 75.82% of active users; mobile 23.93%. Device rows can overlap users. Chrome has 676 active users, Safari 108, Edge 14, and the remaining browsers very small samples. Chrome and Safari engagement rates are nearly identical, around 65%. There is no aggregate evidence here of a broad mobile engagement collapse, but missing conversions prevent comparing device completion rates. Prioritize real form and checkout testing on mobile Safari and Chrome; the tablet sample is too small for decisions.

## 6. Confirmed measurement configuration

| Setting or diagnostic | Observed state | Implication |
|---|---|---|
| Web stream | Website Stream; carelolo.com; G-WJG3HG6EG6 | Stream matches the website |
| Collection health | Data received within the last 48 hours | Collection is active |
| Enhanced measurement | All seven categories enabled | Automatic interaction events are collecting |
| Page-view history changes | Enabled | SPA navigation tracking enabled; check against any manual page views for duplicates |
| Internal traffic filter | Exclude, **Test** | Does not currently exclude matching events |
| Internal traffic definitions | **No rules defined** | No configured IP rules identify staff traffic |
| Unwanted referrals | **No domains configured** | Stripe/verification return attribution remains unaddressed |
| Cross-domain conditions | None; carelolo.com shown as a suggestion | Not itself a defect for one domain; review only if owned domains participate |
| Email redaction | Enabled | Useful existing safeguard, best-effort only |
| URL query-parameter redaction | **Inactive** | No configured query-key redaction |
| Custom dimensions | **0** | No registered dimensions for audience/route/funnel segmentation |
| Event data retention | **2 months** | Limits longer event-level exploratory analysis |
| User data retention | 14 months | Separate setting from event retention |
| Google Ads links | **0** | No linked Ads reporting/conversion integration |
| Search Console links | **0** | Organic search integration not configured |
| Meta | Connection/import setup offered | No cost-performance integration demonstrated |
| Consent diagnostics | Missing EEA consent information; all four listed signals inactive | Verify consent implementation and signal transmission |
| Tag coverage | 464 included; 405 tagged; 45 no recent data; **14 untagged** | Investigate the specific URLs, not a blanket coverage failure |

The 14 untagged URLs consist of **13 admin URLs and one family-request URL**. They do not establish a public landing-page outage. Coverage diagnostics are a starting point for verification, not a full authenticated crawl. Admin pages may intentionally be outside marketing measurement. [Google: tag coverage](https://support.google.com/tagmanager/answer/12270036?hl=en).

### Sensitive URLs need immediate attention

Page reports contain invitation tokens in path segments such as `/family/invitations/[token]/…`, as well as individual operational identifiers. Actual tokens are intentionally omitted here. Their presence in a page-path report confirms collection of the path; this audit did not establish whether any token is still valid or grants access.

Sanitize sensitive route segments before any Analytics event is sent, including automatic page views, referrers, clicks, and form destinations. Use a safe template such as `/family/invitations/[token]` or suppress measurement on sensitive journeys. Do not include care details, free-text requests, names, emails, or access tokens in analytics parameters. Query-key redaction alone cannot solve a token embedded in a path. Google's built-in redaction handles likely emails and specified query parameters, and does not cover every transport or arbitrary sensitive path segment. [Google: data redaction](https://support.google.com/analytics/answer/13544947?hl=en).

The consent diagnostics specifically list inactive `analytics_storage`, `ad_storage`, `ad_user_data`, and `ad_personalization` signals. Verify the default state, user choice updates, and behavior after navigation against the intended consent policy. This is an observed measurement-configuration issue; the browser reports alone do not establish a legal compliance finding. Consent mode communicates choices from a consent interface; it does not supply that interface. [Google: consent mode](https://support.google.com/analytics/answer/9976101?hl=en).

## 7. Prioritized action plan

| Priority | Action | Acceptance evidence |
|---|---|---|
| **P0** | Stop sensitive tokens and personal data entering Analytics | Test invitation/authentication and care-request routes; inspect every emitted URL/parameter; only sanitized values appear |
| **P1** | Implement real family, caregiver, booking, and payment outcomes | One event per successful outcome; failed/retried/cancelled actions do not produce false successes; reconcile against application records |
| **P1** | Exclude confirmed owner activity from France in analysis; separate other staff/testing from customer use | Apply the same France exclusion to both comparison periods; staff/test sessions identifiable; public, family, and caregiver reports have explicit scopes |
| **P1** | Correct Stripe and Didit referral attribution | Test a tagged entry → provider → return journey and verify original source continuity |
| **P1** | Resolve missing consent signals | Validate default and updated choices for analytics and advertising signals on initial load and route transitions |
| **P2** | Validate page views and landing-page completeness | Check direct landing, login redirect, SPA navigation, back/forward, and refresh; no unintended duplicates or missing landing views |
| **P2** | Connect Search Console and applicable campaign reporting; standardize UTMs | Search reports available; paid link clicks retain campaign/source; cost imports reconciled to platform dates and currency |
| **P2** | Improve route/title and audience dimensions | Reports distinguish marketing, family app, caregiver app, and staff use without exposing private identifiers |
| **P2** | Choose suitable event retention | Document whether longer cohort/exploration history is needed and select retention consistent with the data policy |
| **P3** | Evaluate content and channel experiments after measurement cleanup | Compare qualified-family acquisition and caregiver activation by source, not page views alone |

For historical analysis, exclude France based on the owner's confirmation. For ongoing collection, identify the owner and staff through known networks or a reliable application/test-environment marker, rather than assuming every future France visit must be internal. Define rules and validate them in Test mode before activating exclusion; active exclusion affects incoming data permanently. Customer app use should remain measurable separately. The owner's clarification updated this report only; no GA4 configuration was changed. [Google: internal traffic filters](https://support.google.com/analytics/answer/10104470?hl=en).

For provider returns, configure appropriate unwanted referrals after verifying each domain's role. Payment and site-managed verification flows are different from real referring partners such as Craigslist. Do not blanket-ignore all referrals or add third-party providers to cross-domain tracking without a supported implementation. Changes improve future processing and should not be assumed to rewrite historical attribution. [Google: unwanted referrals](https://support.google.com/analytics/answer/10327750?hl=en).

### Proposed business measurement specification

These are implementation recommendations, not events confirmed to exist today.

| Business stage | Suggested event | Success condition |
|---|---|---|
| Account creation | `sign_up` | Account successfully created; distinguish family/caregiver with a coarse role |
| Family demand | `generate_lead` | Valid family inquiry or care request accepted by the application |
| Qualified demand | `qualify_lead` | Explicit qualification milestone, with duplicate protection |
| Converted lead | `close_convert_lead` | Agreed commercial milestone, distinct from an arbitrary form submit |
| Caregiver supply | `caregiver_application_submitted` | Complete application successfully accepted |
| Caregiver activation | `caregiver_activated` | Defined readiness milestone achieved; no identity-document details sent |
| Booking | `booking_confirmed` | Successful confirmed booking; define cancellation handling |
| Payment | `purchase` | Confirmed successful payment; unique transaction ID, currency, and agreed value definition |

Define whether reported payment value means gross booking value or platform revenue, and avoid counting both as the same revenue metric. A visit back from Stripe or the presence of a billing form is not payment confirmation. Keep sensitive operational records in the application; Analytics should receive the minimum non-sensitive outcome metadata needed for measurement.

After implementation, use two distinct reporting views: **acquisition** (qualified family demand, activated caregiver supply, channel/campaign cost) and **product use** (request-to-booking completion, repeat usage, meaningful family/caregiver cohorts). Preserve the pre-fix baseline with an annotation so measurement changes are not confused with business growth.

## Limits and next review

This audit covers what the accessible GA4 reports and settings showed, with a detailed selected-period inventory, plus the owner's subsequent confirmation that all France activity is theirs. It does not claim to inspect every possible dimension combination, private user history, ad account, application transaction, server log, or emitted request. No controlled purchase, signup, invitation acceptance, or consent test was performed. Revenue, leads, duplicate firing, other staff identity, token validity, and implementation causes require application-level validation. France-excluded user/session totals and period growth have not yet been retrieved; the original scorecard remains explicitly unfiltered.

Use the separate [Search Console and SEO audit](SEO_AUDIT_2026-09-13.md) for search visibility and technical SEO. Reassess marketing performance after outcome tracking and traffic separation have accumulated a comparable window of validated data.
