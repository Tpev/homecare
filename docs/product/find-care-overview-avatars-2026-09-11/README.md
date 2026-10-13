# Caregiver avatars and request-care action

Added a 36px circular caregiver avatar beside the single caregiver reply and the next visit's caregiver name on `/family/requests`. It reuses the existing public profile photo and initials fallback. Sofia's current local test profile has no photo, so both instances show SM. Added a top-right Request new care button linking to `/family/requests/create`; it remains right-aligned below the tabs on narrow screens.

Existing action and visit payloads retain their previous values; avatar data is additive. No care or profile records were changed.

Verified 1440px, 390px and 320px layouts, avatar dimensions/fallback, lack of overflow, and navigation to the creation form. Screenshots are in `screenshots/`; results are in `validation.json`. Four existing focused regression tests passed, 57 assertions, in an isolated in-memory database.
