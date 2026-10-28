# Care-hours MySQL rehearsal

`care-hours.php` runs the real application services in separate PHP processes against a newly created `care_hours_concurrency_*` MySQL schema. It does not use SQLite transactions as evidence of concurrency.

Run with a local MySQL account that can create a disposable database:

```powershell
php tests/concurrency/care-hours.php
```

The runner reads local connection settings internally, connects without selecting the configured application database, and refuses remote hosts. It never changes `.env`, selects the application database, or drops an existing schema. An existing database with the generated name causes failure.

For a separate disposable local daemon initialized with an empty-password test root account:

```powershell
$env:CARE_HOURS_CONCURRENCY_PORT = '33308'
php tests/concurrency/care-hours.php
```

Use that override only for an isolated test daemon bound to loopback. The runner does not install, start, reconfigure, or stop the existing MySQL service. Production feature switches remain off; test feature switches are enabled only in each isolated PHP process. HTTP requests and Stripe SDK requests are blocked, Stripe bypass is explicit, and notifications use a fake transport.

The rehearsal applies baseline migrations, creates an ordinary card booking/payment, applies all five care-hours migrations, compares the complete original card rows, and reruns migrations. It then verifies:

- Database cache locks exclude simultaneous critical sections across independent process IDs.
- Competing welcome purchases and repeated paid confirmation produce one welcome award and one credit lot.
- Competing visits reserve disjoint ranges without overspending.
- Approval, settlement reconciliation, and cancellation replays conserve minutes. A stale reservation release racing the initial approval cannot erase delivered care.
- Two recipients racing one gift yield one assignment; a gift claim racing a direct purchase awards the welcome bonus once.
- Two distinct gifts claimed concurrently for the same new self recipient create one private profile/account and award the welcome bonus once.
- Both recovery commands execute successfully with all new-sales/exposure switches paused.

Workers rendezvous at a filesystem barrier before each race. A test query listener briefly holds the first real account row lock (or family row lock for self claims) to produce contention; it does not replace the application's locks. Results record distinct process IDs, overlapping execution intervals, and database locking-query durations. MySQL transaction isolation is reported without changing it.

Evidence is saved under the ignored `tmp/care-hours-concurrency/<database>/` directory. `report.json` is successful only when its `passed` field is `true`; an incomplete report is not a passing run. Job files include fictional gift bearer codes and must remain private. No provider behavior or bank payout is verified by this harness.

To resume a completed migration rehearsal after a harness-only failure, on the same separate daemon:

```powershell
php tests/concurrency/care-hours.php --resume-owned care_hours_concurrency_<exact_run_name>
```

Resume requires the runner's existing ownership record. It never resets a schema or attempts to repair partially applied financial migrations. Database cleanup and daemon shutdown are deliberate operator steps after preserving the report; do not use this runner on an application database.

To run only the self-claim race against an already successful owned rehearsal on the existing disposable loopback daemon:

```powershell
$env:CARE_HOURS_CONCURRENCY_PORT = '33308'
php tests/concurrency/care-hours.php --self-claim-owned care_hours_concurrency_<exact_run_name>
```

This creates fresh fictional fixtures and a separate `self-claim-*.report.json`, preserving the original `report.json`. It performs no migrations or provider operations. The result requires overlapping independent processes, measured contention on the real family lock, one private profile/account, two paid lots, exactly 1,260 available minutes, and one 60-minute welcome award. A stale quote must be reviewed again before its second claim; duplicate confirmations must be harmless.
