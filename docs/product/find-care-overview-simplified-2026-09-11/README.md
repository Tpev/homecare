# Simplified care overview

Removed the Care Overview welcome panel and the Your care/Arrangements summary section from `/family/requests`. Navigation now comes first, followed by Needs your attention and a full-width Next visit. The shared Arrangements tab is now labelled Care request and keeps its existing `/family/care` destination. Labels wrap on narrow screens.

The page retains an accessible heading and its existing support navigation target. Request, booking and account data are unchanged.

Verified the running local app at 1440px, 390px and 320px, including navigation to the Care request tab. No horizontal overflow or browser errors. Screenshots are in `screenshots/`; results are in `validation.json`.

Regression checks: FamilyCareExperienceTest and FamilyDashboardTest passed together, 17 tests and 147 assertions, using an isolated in-memory database. Only existing assertions affected by the removed content or label were updated.
