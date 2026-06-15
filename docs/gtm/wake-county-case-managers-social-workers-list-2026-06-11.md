# Wake County Case Managers and Social Workers Provider List

Date prepared: 2026-06-11

## Files

- `wake-county-case-managers-social-workers-2026-06-11.xlsx`
- `wake-county-case-managers-social-workers-2026-06-11.csv`

## Summary

- Rows: 3567
- Organizations/practices/clinics: 702
- Individual clinicians/providers: 2865
- Rows with a general email in NPPES endpoint data: 45
- Rows with an NPPES Direct messaging endpoint: 78
- Rows retained by Wake ZIP but not matched by Census geocoder: 198

## Source Notes

- Provider source: CMS/NPPES NPI Registry API, version 2.1.
- Wake ZIP source: Wake County GIS `Boundaries/ZipCodes` ArcGIS service.
- County check: U.S. Census batch geocoder. Rows that geocode outside Wake County are removed; rows that do not geocode are retained when their ZIP comes from the Wake County boundary dataset.
- Public office emails are not a standard NPPES field. The spreadsheet separates general endpoint emails from Direct messaging endpoints and marks missing emails as not published in NPPES.
- Individuals are included when their primary NPPES taxonomy is in the target set. Organizations are included when any listed NPPES taxonomy is in the target set.

## Wake ZIPs Used

27501, 27502, 27511, 27513, 27518, 27519, 27520, 27522, 27523, 27526, 27529, 27539, 27540, 27545, 27560, 27562, 27571, 27587, 27591, 27592, 27596, 27597, 27601, 27603, 27604, 27605, 27606, 27607, 27608, 27609, 27610, 27612, 27613, 27614, 27615, 27616, 27617, 27703, 27713

## Target Taxonomy Codes Used

- `171M00000X` Case Manager/Care Coordinator
- `251B00000X` Case Management
- `104100000X` Social Worker
- `1041C0700X` Social Worker, Clinical
- `1041S0200X` Social Worker, School
