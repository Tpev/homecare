# Local redesigned application and sample accounts

The application runs at [http://127.0.0.1:8028/login](http://127.0.0.1:8028/login). The completed [screenshot gallery](http://127.0.0.1:8031/) contains 319 states on desktop and mobile, with 1,914 lifecycle screenshots and linked information inventories. See the [implementation guide](implementation-guide.md) and [final validation](delivery-validation.md).

| Role | Email | Password | Useful starting page |
|---|---|---|---|
| Family | maria.find-care-audit@example.com | password | [Family care](http://127.0.0.1:8028/family/requests) |
| Caregiver | sofia.find-care-audit@example.com | password | [Caregiver work inbox](http://127.0.0.1:8028/caregiver/work-inbox) |
| Administrator | admin.find-care-audit@example.com | password | [User management](http://127.0.0.1:8028/admin/users) |
| Family without a saved care profile | nora.empty-profile.find-care-audit@example.com | password | [Create care](http://127.0.0.1:8028/family/requests/create) |

Use separate browser profiles/private windows to compare roles simultaneously. These are synthetic local accounts with sample care, applicants, invitations, visits, payment states and reviews. Provider operations are isolated by the local runtime; payment rows demonstrate UI states rather than real charges.

The app uses `.tmp/find-care-screen-audit-2026-09-08/find-care-audit.sqlite`. The project `.env`, default database and original September 7 screenshot baseline are unchanged. The database was copied from the verified synthetic baseline to retain the record IDs used by all 175 original screen states; September 7 example dates remain visible as historical/sample state data.

To restart from the repository directory:

```powershell
& 'C:\Program Files\nodejs\node.exe' --preserve-symlinks --preserve-symlinks-main docs/product/find-care-screen-audit-2026-09-08/capture/initialize.mjs
& .\docs\product\find-care-screen-audit-2026-09-08\capture\start-local.ps1
```

Initialization reuses the new isolated database if it already exists, runs only its pending migrations, and creates/refreshes the local demo administrator. It does not replace the database or reset existing sample interactions. Application and gallery logs and process IDs are under the same `.tmp/find-care-screen-audit-2026-09-08` directory.

Desktop/mobile role login results are recorded in [role-smoke.json](role-smoke.json). The family lifecycle gallery and role smoke checks are separate so application access checks cannot inflate the 175-state baseline coverage count.

Additional recurring-offer examples: [Counterproposal](http://127.0.0.1:8028/family/care/8), [Declined offer](http://127.0.0.1:8028/family/care/9), [Cancelled offer](http://127.0.0.1:8028/family/care/10). These are separate synthetic offers with historical source care and no newly generated recurring visits. Recreate them if needed with `node --preserve-symlinks --preserve-symlinks-main docs/product/find-care-screen-audit-2026-09-08/capture/seed-plan-states.mjs`.
