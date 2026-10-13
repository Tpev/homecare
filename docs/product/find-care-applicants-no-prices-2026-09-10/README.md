# Review applicants: remove comparison pricing

Removed hourly care prices, processing-fee lines, the legacy recorded application rate in expanded profile details, and price-based sort choices. The shared applicant card covers All, Shortlisted, Past and selected-caregiver history. Latest/oldest sorting remains. Unused per-applicant quote calculations were removed from rendering.

Applicant notes, qualifications, ratings, experience and actions remain available. Stored rates, pricing agreements, booking snapshots and final hire confirmation pricing remain unchanged.

Browser verification covers one-time and recurring requests at 1440px and 390px, with All, Shortlisted and Past tabs: 12 checks passed, no prices/rate sorting, no horizontal overflow and no browser errors. Expanded details and sort menus were also inspected. Screenshots are in `screenshots/`; results are in `validation.json`.

Browser QA ran against a disposable copy of the pre-cleanup test database with a temporary local server. It did not restore or modify Maria's running local account.

All 14 `CareRecruitmentPolishTest` cases verified: 13 passed in the full run; the remaining case passed after correcting its guarded fixture timestamps and rerunning it. Coverage includes chronological sorting, absent applicant prices, unchanged standard/pair hire quotes and existing booking snapshots.
