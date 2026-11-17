# Partner onboarding and directory

Partners register at `/partner/register` in three steps: business and contact details, services and coverage, then account creation and review. Signup creates a `partner` user and one `partner_profiles` record in a transaction. New profiles start with status `new` and open their dashboard immediately. Existing partners can sign in at `/partner/login` or the normal login page.

The partner dashboard is at `/partner/dashboard`. `/partner/profile` edits the signed-in partner's business, contact details, service categories and coverage. Business contact email is independent of the account's sign-in email. Account settings retain the existing password and sign-in email controls.

## Admin directory

Open **All tools → People → Partner directory** (`/admin/partners`). Search business/contact details or cities, counties and ZIP codes. Combine service-category, state and status filters. Open a profile to review its details, set **New**, **Approved** or **Paused**, and save internal notes. Notes are only available to admins. The signup-link button copies the public onboarding URL for sharing.

Approval and pause are directory statuses. Partners can still access and update their own profile while new or paused. This version does not allocate leads, bill partners or send signup/approval messages.

## Data and access

- Categories are fixed keys in `PartnerOptions`: meal delivery, pharmacy pickup or delivery, home safety improvements, and estate planning and wills.
- Coverage combines one or more US states with either statewide coverage or specific cities, counties and ZIP codes entered as text. This is searchable profile information, not automated geographic matching.
- Only admins can access the directory or update status/internal notes. Partner components recheck role and account access and always retrieve the profile from the authenticated user.
- All signup fields are validated again on submission. Duplicate sign-in email addresses are rejected. Website links accept only HTTP(S).
- The additive migration `2026_11_17_000100_create_partner_profiles_table.php` must be applied before using these pages. No existing lead data is changed.

## Verification

Run `php artisan test --filter=PartnerManagementTest` and `npm run build`. Browser review should cover the three-step signup, editing a partner profile, directory filters/status updates, and narrow mobile layouts.
