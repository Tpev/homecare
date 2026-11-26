"""Generate the exact reference from the allowlist, without connecting to any database."""
import json
from pathlib import Path
import subprocess

root = Path(__file__).resolve().parents[2]
contract = json.loads(subprocess.check_output(["php", str(Path(__file__).with_name("catalog.php"))], text=True))
lines = ["# Reporting export field reference", "", "Generated from `ReportingCatalog.php`; edit the catalog, then regenerate this file.", "",
         f"Schema version: {contract['version']}. Datasets: {len(contract['datasets'])}.", "",
         "All rows are exported. Absent optional tables and columns are declared in the manifest. Missing essential fields reject the snapshot. Each present dataset must also contain its key fields. `created_at`/`updated_at` are included only where the source table retains them. JSON retains only approved nested keys; secrets, URLs and unknown keys are omitted/redacted. Counts of omitted keys and redacted strings appear at each dataset's end.", ""]
for name, definition in contract["datasets"].items():
    lines += [f"## {name}", "", "Key: " + ", ".join(f"`{field}`" for field in definition["key"]) + ".", "",
              "Fields: " + ", ".join(f"`{field}`" for field in definition["fields"]) + ".", ""]
    if definition["json_fields"]:
        lines += ["Structured JSON fields: " + ", ".join(f"`{field}`" for field in definition["json_fields"]) + ".", ""]
    if definition["essential_fields"]:
        lines += ["Essential fields: " + ", ".join(f"`{field}`" for field in definition["essential_fields"]) + ".", ""]
    if definition["utc_fields"]:
        lines += ["UTC-stored instants: " + ", ".join(f"`{field}`" for field in definition["utc_fields"]) + ".", ""]
lines += ["## Approved nested JSON keys", "", "Applied recursively to objects; arrays keep their order and scalar values. Unknown object keys are counted and omitted. Free text remains complete except for link/credential redaction. This protects structured snapshots from accidentally exposing newly added fields.", "",
          ", ".join(f"`{key}`" for key in sorted(contract["json_keys"])) + ".", ""]
destination = root / "docs/reporting/export-fields.md"
destination.write_text("\n".join(lines), encoding="utf-8")
print(destination)
