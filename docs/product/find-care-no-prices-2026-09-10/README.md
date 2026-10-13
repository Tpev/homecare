# Find caregivers: remove comparison pricing

Removed care rates, hourly processing fees and currency from the shared discovery card. This covers recommended caregivers, previously hired caregivers, search results, saved profiles and the invitation picker. Removed the unused discovery quote calculation from the request component.

The invitation history, invitation preview and certification filters contain no generated prices. Existing invitation messages and pricing data remain intact. Platform pricing and confirmation/payment logic are unchanged.

Verified request 44 on desktop (1440px) and mobile (390px): recommendations, invitation preview, search results, saved profiles and invitation history. All 10 browser checks passed with no horizontal overflow or browser errors. Screenshots are in `screenshots/`; browser results are in `validation.json`.

Regression checks: `CareRecruitmentPolishTest` and `CaregiverInvitationExperienceTest`: 32 tests passed, 286 assertions. Browser checks did not send invitations or alter care records.
