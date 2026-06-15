# Wake County Primary Care Provider List

Date prepared: 2026-06-10

## Files

- `wake-county-primary-care-providers-2026-06-10.xlsx`
- `wake-county-primary-care-providers-2026-06-10.csv`

## Summary

- Rows: 3260
- Organizations/practices/clinics: 662
- Individual clinicians: 2598
- Rows with a general email in NPPES endpoint data: 44
- Rows with an NPPES Direct messaging endpoint: 671
- Rows retained by Wake ZIP but not matched by Census geocoder: 262

## Source Notes

- Provider source: CMS/NPPES NPI Registry API, version 2.1.
- Wake ZIP source: Wake County GIS `Boundaries/ZipCodes` ArcGIS service.
- County check: U.S. Census batch geocoder. Rows that geocode outside Wake County are removed; rows that do not geocode are retained when their ZIP comes from the Wake County boundary dataset.
- Public office emails are not a standard NPPES field. The spreadsheet separates general endpoint emails from Direct messaging endpoints and marks missing emails as not published in NPPES.

## Wake ZIPs Used

27501, 27502, 27511, 27513, 27518, 27519, 27520, 27522, 27523, 27526, 27529, 27539, 27540, 27545, 27560, 27562, 27571, 27587, 27591, 27592, 27596, 27597, 27601, 27603, 27604, 27605, 27606, 27607, 27608, 27609, 27610, 27612, 27613, 27614, 27615, 27616, 27617, 27703, 27713

## Primary Care Taxonomy Codes Used

- `207Q00000X` Family Medicine
- `207QA0505X` Family Medicine, Adult Medicine
- `207QG0300X` Family Medicine, Geriatric Medicine
- `207R00000X` Internal Medicine
- `207RA0000X` Internal Medicine, Adolescent Medicine
- `207RG0300X` Internal Medicine, Geriatric Medicine
- `208000000X` Pediatrics
- `2080A0000X` Pediatrics, Adolescent Medicine
- `208D00000X` General Practice
- `261QC1500X` Clinic/Center, Community Health
- `261QF0400X` Clinic/Center, Federally Qualified Health Center (FQHC)
- `261QP2300X` Clinic/Center, Primary Care
- `261QR1300X` Clinic/Center, Rural Health
- `363LA2200X` Nurse Practitioner, Adult Health
- `363LF0000X` Nurse Practitioner, Family
- `363LG0600X` Nurse Practitioner, Gerontology
- `363LP0200X` Nurse Practitioner, Pediatrics
- `363LP2300X` Nurse Practitioner, Primary Care
