## Summary

- What changed:
- Why:
- Scope (caregiver pages/components):

## Screenshots / Video

- Before:
- After:
- Mobile (required for caregiver UI):

## Caregiver UI Checklist

- [ ] Mobile-first layout validated (390px width minimum)
- [ ] One clear primary action is visible on first screen
- [ ] No duplicated context blocks (title/status/info repeated)
- [ ] Status colors match business state (success/warning/danger/info)
- [ ] No empty UI containers (empty footer/card/ghost spacing)
- [ ] Tabs/segmented controls are readable and tappable on mobile
- [ ] Touch targets are at least 44px high for primary interactions
- [ ] Copy is short, action-oriented, and human-readable
- [ ] Dark command surfaces and white content surfaces are used consistently
- [ ] Supports long names/titles without layout break

## Functional QA

- [ ] Shift controls (start/pause/resume/end) still work as expected
- [ ] Chat/support links still route correctly
- [ ] Review states render correctly (draft/submitted)
- [ ] Empty states include a clear next-step CTA

## Testing

- [ ] `php artisan test tests/Feature/Caregiver/CaregiverShiftsExperienceTest.php`
- [ ] `php artisan test tests/Feature/Booking/BookingTrustOpsTest.php`
- [ ] Any additional test(s) added for this PR

## Design Reference

- Caregiver standards: `docs/caregiver-design-guidelines.md`

## Risks / Follow-ups

- Risks:
- Follow-up items:
