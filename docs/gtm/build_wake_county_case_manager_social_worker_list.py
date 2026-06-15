from __future__ import annotations

import json
import zipfile
from collections import OrderedDict
from datetime import date
from pathlib import Path
from xml.etree import ElementTree as ET

import build_wake_county_primary_care_provider_list as base


OUT_DIR = Path(__file__).resolve().parent
RUN_DATE = date.today().isoformat()

TARGET_LABEL = "Case Managers and Social Workers"
OUTPUT_SLUG = "wake-county-case-managers-social-workers"

TARGET_SEARCH_TERMS = [
    "Case Management",
    "Case Manager",
    "Case Manager/Care Coordinator",
    "Social Worker",
]

TARGET_TAXONOMY_CODES = OrderedDict(
    [
        ("171M00000X", "Case Manager/Care Coordinator"),
        ("251B00000X", "Case Management"),
        ("104100000X", "Social Worker"),
        ("1041C0700X", "Social Worker, Clinical"),
        ("1041S0200X", "Social Worker, School"),
    ]
)

HEADERS = [
    "name",
    "record_type",
    "credential",
    "npi",
    "primary_taxonomy",
    "matched_case_management_social_work_taxonomies",
    "all_taxonomies",
    "phone",
    "fax",
    "email",
    "email_source",
    "direct_endpoint",
    "address_1",
    "address_2",
    "city",
    "state",
    "zip",
    "geocoded_county",
    "geocode_match_status",
    "nppes_last_updated",
    "source_url",
    "notes",
]


def configure_base_module() -> None:
    base.LOG_PATH = OUT_DIR / f"{OUTPUT_SLUG}-build.log"
    base.PRIMARY_CARE_SEARCH_TERMS = TARGET_SEARCH_TERMS
    base.PRIMARY_CARE_TAXONOMY_CODES = TARGET_TAXONOMY_CODES
    base.HEADERS = HEADERS


def rename_taxonomy_match_column(rows: list[dict[str, str]]) -> list[dict[str, str]]:
    for row in rows:
        row["matched_case_management_social_work_taxonomies"] = row.pop(
            "matched_primary_care_taxonomies", ""
        )
    return rows


def fill_missing_phones(
    rows: list[dict[str, str]], records: dict[str, dict[str, object]]
) -> list[dict[str, str]]:
    for row in rows:
        if row.get("phone"):
            continue

        record = records.get(row["npi"], {})
        addresses = (record.get("practiceLocations") or []) + (record.get("addresses") or [])
        fallback_phone = ""
        for address in addresses:
            fallback_phone = base.format_phone(address.get("telephone_number"))
            if fallback_phone:
                break

        if fallback_phone:
            row["phone"] = fallback_phone
            row["notes"] = "Phone from alternate NPPES address for same NPI"

    return rows


def write_summary(
    rows: list[dict[str, str]], wake_zip_entries: list[dict[str, str]], path: Path
) -> None:
    organizations = sum(1 for row in rows if row["record_type"] == "Organization")
    individuals = sum(1 for row in rows if row["record_type"] == "Individual")
    with_direct = sum(1 for row in rows if row["direct_endpoint"])
    with_general_email = sum(1 for row in rows if row["email"])
    unmatched = sum(1 for row in rows if row["geocode_match_status"].upper() != "MATCH")
    zip_list = ", ".join(sorted({entry["zip"] for entry in wake_zip_entries}))
    taxonomy_list = "\n".join(
        f"- `{code}` {desc}" for code, desc in TARGET_TAXONOMY_CODES.items()
    )

    content = f"""# Wake County {TARGET_LABEL} Provider List

Date prepared: {RUN_DATE}

## Files

- `{OUTPUT_SLUG}-{RUN_DATE}.xlsx`
- `{OUTPUT_SLUG}-{RUN_DATE}.csv`

## Summary

- Rows: {len(rows)}
- Organizations/practices/clinics: {organizations}
- Individual clinicians/providers: {individuals}
- Rows with a general email in NPPES endpoint data: {with_general_email}
- Rows with an NPPES Direct messaging endpoint: {with_direct}
- Rows retained by Wake ZIP but not matched by Census geocoder: {unmatched}

## Source Notes

- Provider source: CMS/NPPES NPI Registry API, version 2.1.
- Wake ZIP source: Wake County GIS `Boundaries/ZipCodes` ArcGIS service.
- County check: U.S. Census batch geocoder. Rows that geocode outside Wake County are removed; rows that do not geocode are retained when their ZIP comes from the Wake County boundary dataset.
- Public office emails are not a standard NPPES field. The spreadsheet separates general endpoint emails from Direct messaging endpoints and marks missing emails as not published in NPPES.
- Individuals are included when their primary NPPES taxonomy is in the target set. Organizations are included when any listed NPPES taxonomy is in the target set.

## Wake ZIPs Used

{zip_list}

## Target Taxonomy Codes Used

{taxonomy_list}
"""
    path.write_text(content, encoding="utf-8")


def set_workbook_title(path: Path) -> None:
    temp_path = path.with_suffix(".tmp.xlsx")
    namespace = {"main": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}

    with zipfile.ZipFile(path, "r") as source:
        with zipfile.ZipFile(temp_path, "w", zipfile.ZIP_DEFLATED) as target:
            for item in source.infolist():
                data = source.read(item.filename)
                if item.filename == "xl/workbook.xml":
                    root = ET.fromstring(data)
                    sheet = root.find("main:sheets/main:sheet", namespace)
                    if sheet is not None:
                        sheet.set("name", "Case social work")
                    data = ET.tostring(root, encoding="utf-8", xml_declaration=True)
                target.writestr(item, data)

    temp_path.replace(path)


def main() -> None:
    configure_base_module()
    base.LOG_PATH.write_text(
        f"Wake {TARGET_LABEL.lower()} provider build started {RUN_DATE}\n",
        encoding="utf-8",
    )

    base.log("Fetching Wake County ZIPs")
    wake_zip_entries = base.fetch_wake_zip_entries()
    wake_zips = {entry["zip"] for entry in wake_zip_entries}
    base.log(f"Found {len(wake_zips)} Wake ZIPs")

    raw_path = OUT_DIR / f"{OUTPUT_SLUG}-nppes-raw-{RUN_DATE}.json"
    if raw_path.exists():
        base.log(f"Loading existing raw NPPES cache from {raw_path}")
        records = json.loads(raw_path.read_text(encoding="utf-8"))
    else:
        base.log("Fetching NPPES records")
        records = base.fetch_nppes_records(wake_zip_entries)
        with raw_path.open("w", encoding="utf-8") as handle:
            json.dump(records, handle)
        base.log(f"Saved raw NPPES cache to {raw_path}")

    base.log("Filtering target candidates")
    rows = rename_taxonomy_match_column(base.build_candidate_rows(records, wake_zips))
    rows = fill_missing_phones(rows, records)
    base.log(f"Candidate location rows: {len(rows)}")

    candidates_path = OUT_DIR / f"{OUTPUT_SLUG}-candidates-{RUN_DATE}.csv"
    base.write_csv(rows, candidates_path)
    base.log(f"Wrote pre-geocode candidate rows to {candidates_path}")

    base.log("Geocoding candidate addresses")
    geocoded_rows = base.geocode_rows(rows)
    wake_rows = base.filter_to_wake_or_unmatched(geocoded_rows)
    wake_rows.sort(key=lambda row: (row["city"], row["name"], row["address_1"], row["npi"]))
    base.log(f"Wake rows after geocode filter: {len(wake_rows)}")

    csv_path = OUT_DIR / f"{OUTPUT_SLUG}-{RUN_DATE}.csv"
    xlsx_path = OUT_DIR / f"{OUTPUT_SLUG}-{RUN_DATE}.xlsx"
    summary_path = OUT_DIR / f"{OUTPUT_SLUG}-list-{RUN_DATE}.md"

    base.write_csv(wake_rows, csv_path)
    base.write_xlsx(wake_rows, xlsx_path)
    set_workbook_title(xlsx_path)
    write_summary(wake_rows, wake_zip_entries, summary_path)

    base.log(f"Wrote {csv_path}")
    base.log(f"Wrote {xlsx_path}")
    base.log(f"Wrote {summary_path}")


if __name__ == "__main__":
    main()
