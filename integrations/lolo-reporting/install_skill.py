"""Install the versioned LoLo reporting skill for the current Codex user."""
import json
import os
from pathlib import Path
import shutil

base = Path(__file__).resolve().parent
source = base / "skill/lolo-business-reports"
codex_root = Path(os.environ.get("CODEX_HOME", Path.home() / ".codex"))
destination = codex_root / "skills/lolo-business-reports"
if destination.exists() and not (destination / "scripts/runtime.json").exists():
    raise SystemExit("A skill already exists at this name without this installer's runtime marker. Review it before replacing files.")
shutil.copytree(source, destination, dirs_exist_ok=True, ignore=shutil.ignore_patterns("__pycache__", "*.pyc"))
(destination / "scripts/runtime.json").write_text(json.dumps({"cli": str(base / "lolo_reporting.py")}, indent=2) + "\n", encoding="utf-8")
print(destination)
