"""Run the installed local reporting client without opening or displaying credentials."""
import json
from pathlib import Path
import subprocess
import sys

settings = Path(__file__).with_name("runtime.json")
runtime = Path(json.loads(settings.read_text(encoding="utf-8"))["cli"]) if settings.exists() else Path(__file__).resolve().parents[3] / "lolo_reporting.py"
if not runtime.is_file():
    sys.exit("LoLo reporting runtime was moved. Run install_skill.py from the current repository checkout.")
sys.exit(subprocess.call([sys.executable, str(runtime), *sys.argv[1:]]))
