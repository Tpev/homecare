# Automatic request expiration

The Laravel scheduler runs `homecare:expire-care-requests` every minute, without overlapping runs. The command scans all eligible records, including requests created before this release. No data migration or deletion is required.

Deploy this feature with the application's current database migrations already applied. Preview and immediately process the backlog with:

```sh
php artisan homecare:expire-care-requests --dry-run
php artisan homecare:expire-care-requests
```

Open or draft one-time requests expire once their known service end is at least 24 elapsed hours ago. Posting time and start time are never used as substitutes for an absent end. Invalid end-before-start schedules are left alone. Recurring, system-generated, plan-linked and shared-care requests are excluded. Any current or released booking, recorded first hire, or hired application prevents expiration, regardless of booking or payment status.

The command locks each request using the same lock as hiring and rechecks its eligibility before changing status. Pending invitations become expired; applications, conversations, messages, bookings and financial records are retained. It sends no notifications. Repeated runs are safe.

Families find these records in **Care → History → Expired requests** and the existing **Past care** filter. Caregivers find requests they applied to or were invited to in **Work Inbox → History**. Expired requests do not contribute to active inbox counts or family request actions. Visits and payment history remain in their existing views.

Validation: `php artisan test --compact --filter='CareRequestExpirationTest|CaregiverWorkInboxTest|FamilyCareExperienceTest|CareHistoryTest|CareRequestWithdrawalPresentationTest'`.
