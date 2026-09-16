# Family onboarding wizard — standalone preview

The real application integration is now implemented. See [implementation and rollout notes](IMPLEMENTATION.md) for the integrated local app on port 8033, test accounts and deployment instructions. The preview below remains a static design reference.

Local preview: http://127.0.0.1:8032/previews/family-onboarding/

## Flow

1. **Who is receiving care?** Me or a family member. A family member requires their name and relationship to the account holder.
2. **Where will care happen?** Street address, optional apartment, city, state, and ZIP code.
3. **Care preferences.** Optional caregiver notes and care preferences. Wording adapts when the recipient is the account holder.
4. **Free welcome visit.** An optional, free, one-hour welcome visit from a LoLo team member. Yes reveals the preferred date and a time range: Morning (9 am–12 pm), Noon (12–2 pm), or Afternoon (2–5 pm). No continues without scheduling. The selected range must start at least 24 hours ahead in America/New_York, matching the application's North Carolina scheduling default. Both the form and summary explain that the team will confirm the final time by text.
5. **Your first request.** Post a request → review caregivers → hire a caregiver → visit. The final button opens `/family/requests/create`, the existing authenticated family route.

## Preview scope

The three files in `public/previews/family-onboarding/` are independent of the production signup flow. They reuse the existing local LoLo wordmark and fonts. Form values stay in memory during navigation; refreshing or leaving the preview discards them. No profile is saved and no welcome visit is booked. The request-creation destination uses the app's existing authentication and does not yet receive these preview values.

The production integration can follow once the design and flow are approved: connect signup, save recipient/address/notes, persist the visit preference, and prefill the first request. Server-side validation will be needed with that integration.

## Run locally

From the repository root:

```powershell
php artisan serve --host=127.0.0.1 --port=8032 --no-reload
```

No frontend build or additional packages are needed for this preview.

## Verification

```powershell
node docs/product/family-onboarding-2026-09-15/qa.mjs
```

33 browser checks passed, with no browser runtime errors. Checks include required fields, optional notes, both recipient and visit branches, preserving inputs while navigating, preventing invalid forward navigation, the 24-hour boundary from a browser in Paris, revalidating stale times at handoff, the request-creation destination, and overflow checks for all five steps at 390px and 320px widths. The request destination is intercepted during testing to avoid interacting with the application.

16 screenshots cover the five steps at desktop and mobile widths plus the conditional family-member fields. Desktop, mobile, and narrow scheduling screenshots were visually reviewed. Results are in `validation.json`; screenshots are in `screenshots/`.
