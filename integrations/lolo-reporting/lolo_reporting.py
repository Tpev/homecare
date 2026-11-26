#!/usr/bin/env python3
"""Local entry point. Server access is limited to the explicit download command."""
import argparse
import json
from pathlib import Path
import sys

from downloader import DEFAULT_HOME, SnapshotError, download, dumps, initialize, resolve_snapshot, verify, write_json
from reports import Reporting, Snapshot, caregiver_evidence, changes, evidence


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--home", type=Path, default=DEFAULT_HOME)
    commands = parser.add_subparsers(dest="command", required=True)
    init = commands.add_parser("init")
    init.add_argument("--origin", default="https://carelolo.com")
    fetch = commands.add_parser("download")
    fetch.add_argument("--allow-local-http", action="store_true")
    fetch.add_argument("--timeout", type=int, default=300)
    fetch.add_argument("--max-bytes", type=int, default=10 * 1024**3)
    for name in ("report", "evidence", "verify", "diff"):
        command = commands.add_parser(name)
        command.add_argument("--snapshot", type=Path)
        command.add_argument("--output", type=Path)
        if name == "report":
            command.add_argument("--mode", choices=("status", "weekly", "hot", "searches", "quality"), default="status")
            command.add_argument("--days", type=int, default=7)
            command.add_argument("--timezone")
            command.add_argument("--exclusions", type=Path)
            command.add_argument("--limit", type=int, default=20, help="Maximum entries per queue; 0 returns all.")
        elif name == "evidence":
            selection = command.add_mutually_exclusive_group(required=True)
            selection.add_argument("--lead", type=int)
            selection.add_argument("--request", type=int)
            selection.add_argument("--caregiver", type=int, help="Caregiver user ID")
        elif name == "diff":
            command.add_argument("--previous", type=Path, required=True)
    args = parser.parse_args(argv)
    args.home = args.home.resolve()
    try:
        if args.command == "init":
            result = initialize(args.home, args.origin, Path(__file__).resolve().parents[2] / "config/reporting.php")
        elif args.command == "download":
            result = download(args.home, args.allow_local_http, args.timeout, args.max_bytes)
        else:
            path = resolve_snapshot(args.home, args.snapshot)
            if args.command == "verify":
                result = {"verified": True, "snapshot_id": verify(path, full=True)["manifest"]["snapshot_id"]}
            else:
                snapshot = Snapshot(path)
                try:
                    if args.command == "report":
                        exclusions_path = args.exclusions or args.home / "exclusions.json"
                        exclusions = json.loads(exclusions_path.read_text(encoding="utf-8")) if exclusions_path.exists() else {}
                        if args.limit < 0:
                            raise SnapshotError("Report limit must be nonnegative.")
                        result = Reporting(snapshot, args.days, args.timezone, exclusions).report(args.mode, args.limit)
                    elif args.command == "evidence":
                        result = caregiver_evidence(snapshot, args.caregiver) if args.caregiver is not None else evidence(snapshot, args.lead, args.request)
                    else:
                        previous = Snapshot(args.previous.resolve())
                        try:
                            result = changes(snapshot, previous)
                        finally:
                            previous.close()
                finally:
                    snapshot.close()
        if getattr(args, "output", None):
            write_json(args.output, result)
            print(dumps({"written": str(args.output.resolve())}))
        else:
            print(dumps(result))
        return 0
    except (SnapshotError, FileNotFoundError) as exc:
        print(f"Reporting error: {exc}", file=sys.stderr)
        return 1
    except Exception:
        # Avoid accidentally printing raw customer data, network headers or credentials in a traceback.
        print("Reporting failed. Check local configuration, file permissions and server availability.", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
