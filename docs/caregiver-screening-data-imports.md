# Public data imports for caregiver screening

## Verified formats and deployment status

The default adapters were checked against the five official ZIP files supplied on 2026-10-13. Local files are copied into private archives, parsed into a staged source version, and activated only after validation. No application database was migrated or populated during development. Full-data checks use a separate testing SQLite database, described below.

Official references: [NC DAC downloads](https://webapps.doc.state.nc.us/opi/downloads.do?method=view), [DAC public table structure](https://www.doc.state.nc.us/offenders/PublicTables.pdf), and [NC SBI public download](https://sexoffender.ncsbi.gov/stats.aspx).

The default local adapters are usable with the supplied files. SBI's network download and import succeeded on the deployment host on 2026-10-13. Its download control submits an ASP.NET postback, which the downloader reproduces with fresh hidden fields and cookies. All four DAC ZIP endpoints passed verified HTTPS header checks on that host; a complete DAC network import still needs confirmation. Direct official requests from the development environment timed out. A browser proxy address must never be used as a production endpoint.

Missing, malformed, or stale source versions prevent finalizing a screening search. They do not restrict legacy or recurring-review caregivers.

## NC DAC

All four ZIP files are required. Each contains the matching `.des` descriptor and fixed-width `.dat` file. Descriptors provide official field labels, byte offsets, and lengths. The default descriptor hashes are pinned to the inspected files; changes require review before import.

| Archive | Type | Identifier | Indexed name fields | Row width | Supplied rows |
| --- | --- | --- | --- | ---: | ---: |
| OFNT3AA1.zip | profile | CMDORNUM | None; supplies demographics and CMCLBRTH birth date | 429 | 1,299,355 |
| OFNT3BB1.zip | commitment | CMDORNUM | CMSCFSTN, CMSCMIDN, CMSCLSTN | 307 | 3,990,445 |
| OFNT3CE1.zip | sentence | CMDORNUM | None | 937 | 4,843,113 |
| APPT7AA1.zip | probation | CDDORNUM | CDSFSTNM, CDSMIDNM, CDSLSTNM | 391 | 1,200,382 |

Widths exclude line terminators. AA contains no names; searching AA alone would miss the supplied names. Names from every commitment/probation row are indexed against the source ID, including different names on different rows. These four files do not provide a separate comprehensive alias table. All published fields, including suffixes and source codes, remain in the evidence; no broader alias coverage is claimed. The application does not infer undocumented relationships among commitment and sentence subrecords.

Approved descriptor SHA-256 values:

| Table | SHA-256 |
| --- | --- |
| OFNT3AA1 | `7fe77769b1590a6731d215960e2fae1161e0f6aaa4891b967ead3849745f3310` |
| OFNT3BB1 | `857ccc75e587e7c15436ad8dca7414764ab08fc606392556d7c3b2fe3b94e44e` |
| OFNT3CE1 | `f0f3c7bf3df7d2749da40021cacba35aece16fdd69bc7fc173557c23289c5453` |
| APPT7AA1 | `b00252add83de8179f4a0644a3d528bb7058d23325647be6bbd9a072672dd7a0` |

`SCREENING_DAC_FIELDS` and `SCREENING_DAC_DESCRIPTOR_HASHES` accept reviewed JSON overrides. Field mappings support `offender_id`, `first_name`, `middle_name`, `last_name`, `full_name`, `date_of_birth`, alias fields, explicit name ordering, and documented alias separators. DAC text defaults to Windows-1252. Do not use synthetic fixture mappings in production.

Per-file URL overrides are `SCREENING_DAC_OFNT3AA1_URL`, `SCREENING_DAC_OFNT3BB1_URL`, `SCREENING_DAC_OFNT3CE1_URL`, and `SCREENING_DAC_APPT7AA1_URL`. Allowed hosts are `www.doc.state.nc.us`, `webapps.doc.state.nc.us`, and `opus.doc.state.nc.us`. Defaults use `https://opus.doc.state.nc.us/offenders/<TABLE>.zip`, matching the official download page and supplied file metadata. On 2026-10-13, the deployment host verified all four endpoints over HTTPS: each returned HTTP 200 with ZIP content type. The earlier `www.doc.state.nc.us` endpoint failed certificate-chain validation on that host. Keep TLS verification enabled and inspect any environment overrides before deployment; redirects and non-HTTPS URLs are rejected.

## NC SBI

The default filename is **publicsor.zip**. Its verified contents are nine fixed-width text tables with column headers, a dash ruler, and space separators. The bundled layout document is dated 2014-10-17. Exact rendered widths were verified against the actual export. Every header, ruler, separator, and row width is checked; text uses Windows-1252.

| ZIP member | Type | Supplied rows |
| --- | --- | ---: |
| Public Individual Information.txt | profile | 31,241 |
| Public Name Information.txt | alias | 108,108 |
| Public Conviction Name Information.txt | conviction_name | 100,578 |
| Public BirthDate Information.txt | birth_date | 4,132 |
| Public Offense Information.txt | offense | 57,696 |
| Public Address Information.txt | address | 238,976 |
| Public NonResident Information.txt | nonresident | 204 |
| Public ScarMarkTattoo Information.txt | physical_mark | 129,452 |
| Public Violation Information.txt | violation | 1,710 |

All tables join through `SexRegistrationNumber`. `FullName` is parsed as `SURNAME,GIVEN MIDDLE` from individual, name, and conviction-name tables. Every available name is indexed. Names collapse only when the normalized full text and first/last components are identical within one source identity; distinct component boundaries remain searchable. Original repeated evidence rows remain stored. Birth dates are identifying evidence and never automatically discard a name candidate.

`SCREENING_SBI_LAYOUT` defaults to `{"format":"sbi_public_fixed_width"}`. Future reviewed flat CSV/XML layouts can specify `format`, exact ZIP `member`, full `headers`, semantic `fields`, and XML `record_element`. CSV BOM, quote state, and column counts are validated. XML DTD/entities, nested fields, and repeated fields are rejected. Unknown formats do not silently pass.

Without `SCREENING_SBI_DOWNLOAD_URL`, the downloader loads `https://sexoffender.ncsbi.gov/stats.aspx`, reads fresh form fields/cookies, and posts `__EVENTTARGET=datadownloadbutton` to the same official endpoint. The deployment host's read-only inspection on 2026-10-13 confirmed form ID `_ctl0`, method `post`, and action `./stats.aspx`; this same-page relative action is accepted alongside `/stats.aspx`, `stats.aspx`, and the exact official URL. The POST destination remains fixed to the official HTTPS URL, with redirects disabled. The optional URL overrides this with a verified direct HTTPS file endpoint; it must not be the statistics HTML page. The deployment host subsequently completed the network import with 32,003 source IDs.

The same host returned only `__VIEWSTATE` and `__VIEWSTATEGENERATOR` hidden inputs. Both must be present and nonempty in each fresh page. The downloader supplies `__EVENTTARGET=datadownloadbutton` and an empty `__EVENTARGUMENT` from the validated download action even when their inputs are absent, and overrides any existing values. Other supplied hidden fields, including `__EVENTVALIDATION`, are retained; absent state tokens are never fabricated.

## Source coverage

Some supporting records have an ID absent from the primary table. These are preserved as explicit source-only profiles, with every published related row and all available names. No identity, name, birth date, or primary record is invented. Candidate evidence warns about the missing primary record.

Records without a usable published name are retained and counted as a limitation. They cannot be found through name matching. **No Match means no candidate among the available names in the recorded versions**, not that nameless records were checked or that the person has a nationwide clearance.

Each import stores `file_manifest.name_coverage` with `profile_count` (union of all IDs), `primary_profiles`, `source_only_profiles`, `searchable_profiles`, `profiles_without_names`, and an array of human-readable `limitations`. Full supplied-file results are:

| Source | Total source IDs | Primary profiles | Source-only profiles | IDs with searchable names | IDs without usable names |
| --- | ---: | ---: | ---: | ---: | ---: |
| NC DAC | 1,299,482 | 1,299,355 | 127 | 1,297,484 | 1,998 |
| NC SBI | 32,003 | 31,241 | 762 | 32,003 | 0 |

Every supplied row is retained, plus the explicit source-only profiles. DAC's 1,998 nameless IDs remain stored but cannot produce a name candidate. These totals count identifiers within each source; they do not deduplicate people across sources.

Candidate snapshots contain `missing_primary_record`, `coverage_warning`, `source_coverage`, and DAC `field_labels` keyed by table and original field code. Search results include coverage keyed by source even with zero candidates. The review screen flags missing primary identity records on affected matches; full source coverage remains available in stored candidate evidence and search audit records. Retain import versions referenced by reviews.

## Configuration and safeguards

Configuration is in `config/screening.php`. Required sources are `nc_dac` and `nc_sbi`. The maximum source age defaults to 14 days (`SCREENING_MAX_AGE_DAYS`), separate from the six-month caregiver review cycle. Minimum primary profile counts default to 10,000 DAC and 1,000 SBI (`SCREENING_DAC_MINIMUM_RECORDS`, `SCREENING_SBI_MINIMUM_RECORDS`); source-only IDs cannot inflate this check and conceal a truncated primary file.

PHP requires ZIP, mbstring, and XMLReader. Production requires a shared lock-capable cache; each source holds a six-hour import lock. Do not run concurrent imports through unshared caches. Monitor actual runtime on the deployment database.

Limits are 512 MiB compressed per file, 8 GiB declared uncompressed per archive, and 64 KiB per record. The supplied OFNT3CE1.dat alone is 4,542,839,994 bytes. Only explicitly named ZIP members are streamed to generated filenames. Traversal, symlinks, encryption, ambiguous members, size mismatches, and CRC failures are rejected.

Required tables must be nonempty, primary IDs unique, and row layouts valid. Supporting references reconcile to actual or explicit source-only profiles. A table losing over 20% of the prior version's row count prevents activation. Review legitimate large removals before changing `minimum_retained_ratio`; no CLI bypass exists. Rows are not skipped to make a source appear clean.

Activation occurs only after validation. Failed replacements preserve the prior active version and remove partial searchable rows. Failed private archives and sanitized failure metadata remain for diagnosis. The previous version is usable only until its freshness limit.

Import failures log the exception class, PHP filename and line number, plus a sanitized message. Database bindings and unexpected exception messages are not logged because they can contain source records. Download cleanup preserves the original failure even if the HTTP transport has already closed the private output handle.

## Operations

Automatic dataset refresh is disabled by default through `SCREENING_AUTO_REFRESH_ENABLED=false`. This gates only the scheduled `screening:refresh-datasets` event; explicit import/refresh commands, caregiver queue synchronization and audit export remain available. Keep it disabled until the first imports and storage plan have been verified. Check the actual filesystems for the database, temporary files and private archives; application-disk free space alone does not establish database capacity. Include database indexes and binary logs, simultaneous old/new versions, retained archives, and failed-import workspace. The SQLite measurement below is not a MySQL sizing guarantee. Historical index pruning is a separate manual operation and does not remove archives or automatically return allocated database storage to the operating system.

After validating those requirements, opt in with `SCREENING_AUTO_REFRESH_ENABLED=true` in the protected deployment environment and deploy through `deploy.sh`, which rebuilds the inactive release's configuration cache. Do not rebuild the active release's configuration cache manually. Verify the cached flag and scheduler registration:

```sh
php artisan tinker --execute='echo "automatic_dataset_refresh=".(config("screening.auto_refresh_enabled", false) ? "enabled" : "disabled").PHP_EOL;'
php artisan schedule:list --no-ansi
```

When disabled, the schedule lists `screening:sync` at 05:00 and `screening:export-audit` every minute, with no `screening:refresh-datasets` entry. When enabled, refresh appears at 03:00 in the application timezone. This switch does not cancel an import already running; let that operation finish or review its state separately. Disabling refresh leaves any existing source snapshot subject to the normal freshness limit.

After deployment migrations and verified source configuration:

```sh
php artisan screening:import nc_dac
php artisan screening:import nc_sbi
php artisan screening:refresh-datasets
```

For local files, retain the four DAC ZIP filenames and publicsor.zip. Supply the actual acquisition timestamp; copying an old file does not refresh it:

```sh
php artisan screening:import nc_dac --directory=/private/official-files --downloaded-at=2026-10-13T08:00:00Z
php artisan screening:import nc_sbi --directory=/private/official-files --downloaded-at=2026-10-13T08:00:00Z
```

These timestamps are examples. Daily refresh is separate from caregiver queue scheduling. Failed attempts appear separately from the active version.

Archives are retained under `storage/app/private/screening/imports/<import-id>` and must not be web-accessible. Include them in protected backups. DAC contains millions of rows; allow substantial database storage, working disk, and import runtime. Original evidence is JSON in addition to indexed names, and replacement activation retains both versions. See [screening retention maintenance](caregiver-screening-retention.md) for the manual preview/`--execute` cleanup command. Retain metadata referenced by audit records; candidate snapshots preserve relevant evidence independently of source replacements.

## Matching and validation evidence

Search indexes normalized full names and first/last components, handling case, diacritics, apostrophes, punctuation, and spacing. Two bounded indexed lookups are combined by unique name ID before enforcing the 1,000-entry limit. First/last matches may have different middle names; the explanation states this. There is no fuzzy matching or automatic identity confirmation. Incomplete identities, invalid sources, changed source versions, and more than 1,000 matching name entries fail explicitly rather than yielding No Match or silently truncating results. Final decisions require the searched versions to remain fresh and active, with transaction locks against concurrent activation.

`tests/Feature/Screening/ScreeningDatasetImportTest.php` uses in-memory SQLite and invented ZIP rows with actual DAC descriptors/SBI table shapes. Its 25 tests / 92 assertions cover related-table names, source-only/nameless coverage, distinct name component boundaries, overlapping lookup deduplication and combined overflow, BOM headers, invalid layouts, rollback, traversal, freshness, offset timestamps, changed versions, count drops, and incomplete-name rejection.

Full-data validation on 2026-10-13 completed against only `tmp/screening-data-validation.sqlite` with `APP_ENV=testing` and private raw archives under `tmp/screening-source-validation`. Both versions activated after complete row parsing and validation. The user's original ZIP files were unchanged, and no application database was populated.

| Source | All published rows retained | Original full import time | Final indexed name entries |
| --- | ---: | ---: | ---: |
| NC DAC | 11,333,295 | 5,370.76 seconds (89.5 minutes) | 2,187,649 |
| NC SBI | 672,097 | 192.16 seconds | 107,910 |

Validation exposed two issues that were repaired in the implementation and the isolated database, without repeating the bulk imports:

- Name uniqueness originally collapsed identical full text with different first/last boundaries. The final schema preserves these variants. A narrow reconciliation of the five archived name-bearing tables restored 2,045 DAC and 204 SBI entries in 191.77 seconds, including index replacement. Evidence rows, source activation metadata, and searchable-ID coverage stayed unchanged.
- Offset acquisition timestamps must be converted to the application's timezone before Eloquent stores timestamp text. The importer now does this, with a non-UTC regression. The isolated database's two `downloaded_at` values were corrected to preserve the original acquisition instants (DAC 09:42:42 UTC and SBI 09:42:22 UTC), verified against unchanged source archives. No other metadata changed.

The first real-data search also exposed an index-planning problem with a combined `OR` query: positive searches took 14–16 seconds while scanning an import's name entries. Separate bounded full-name and first/last lookups fixed it. A final read-only smoke run found the selected expected ID in both sources: SBI 12.41 ms (two candidates), DAC 4.72 ms (one candidate), and a zero-hit search 2.67 ms with both source versions and their coverage retained. SQLite query plans confirmed covering indexes using all full-name or first/last lookup columns. These are single local smoke measurements after validation, not a production latency guarantee or a load benchmark.

The completed SQLite database occupied 16,871,813,120 bytes (about 16.87 GB / 15.71 GiB), excluding private archives and extraction workspace. Provision for simultaneous old/new source versions, temporary extracted files, and protected archive retention. The measured full DAC import on this Windows SQLite environment took nearly 90 minutes; benchmark the deployment database and schedule refreshes with enough time and disk space. Automated official-host acquisition still requires deployment connectivity verification; the actual downloaded-file parsing, joins, name coverage, and searches above were fully exercised.
