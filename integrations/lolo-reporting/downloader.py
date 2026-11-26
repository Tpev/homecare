"""Validated read-only snapshot transport. No third-party network client required."""
from __future__ import annotations

import csv
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import sqlite3
import subprocess
import tempfile
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone

VERSION = 1
NAME = re.compile(r"^[a-z][a-z0-9_]{0,100}$")
MAX_LINE = 32 * 1024 * 1024
DEFAULT_HOME = Path.home() / ".lolo-reporting"
LINK_KEYS = ("lead_id", "family_account_id", "care_request_id", "care_booking_id",
             "caregiver_user_id", "user_id", "care_plan_id", "source_care_request_id",
             "care_request_conversation_id", "support_ticket_id", "family_onboarding_id",
             "caregiver_profile_id", "continuous_coverage_plan_id", "continuous_coverage_shift_id",
             "care_arrangement_id", "care_hour_account_id", "care_hour_booking_funding_id")


class SnapshotError(ValueError):
    pass


def dumps(value):
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"), allow_nan=False)


def digest(path: Path):
    result = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            result.update(block)
    return result.hexdigest()


def private_directory(path: Path):
    path.mkdir(parents=True, exist_ok=True, mode=0o700)
    if path.is_symlink():
        raise SnapshotError("Private data directory must not be a symlink.")
    if os.name == "nt":
        identity = subprocess.run(["whoami", "/user", "/fo", "csv", "/nh"],
                                  capture_output=True, text=True, check=True)
        sid = next(csv.reader(identity.stdout.strip().splitlines()))[-1]
        if not re.fullmatch(r"S-1-[0-9-]+", sid):
            raise SnapshotError("Could not identify the Windows account for private ACLs.")
        subprocess.run(["icacls", str(path), "/inheritance:r", "/grant:r",
                        f"*{sid}:(OI)(CI)F", "*S-1-5-18:(OI)(CI)F"],
                       check=True, capture_output=True)
    else:
        path.chmod(0o700)


def write_json(path, value):
    with Path(path).open("w", encoding="utf-8", newline="\n") as file:
        file.write(dumps(value) + "\n")
    if os.name != "nt":
        Path(path).chmod(0o600)


def endpoint(origin: str, allow_local=False):
    parsed = urllib.parse.urlsplit(origin)
    local = parsed.hostname in ("localhost", "127.0.0.1", "::1")
    if (parsed.scheme != "https" and not (allow_local and local and parsed.scheme == "http")) or not parsed.hostname:
        raise SnapshotError("Use HTTPS (HTTP is allowed only for explicit local development).")
    if parsed.username or parsed.password or parsed.query or parsed.fragment or parsed.path not in ("", "/"):
        raise SnapshotError("Origin must contain only scheme, hostname and optional port.")
    return origin.rstrip("/") + "/api/reporting/v1/export"


def initialize(home: Path, origin: str, server_config: Path | None = None):
    """Prepare once, reuse thereafter. Only the token's verifier goes into deployable code."""
    endpoint(origin)
    private_directory(home)
    token_file = home / "token"
    reused = token_file.exists()
    if reused:
        token = token_file.read_text(encoding="ascii").strip()
    else:
        token = secrets.token_urlsafe(48)
        fd = os.open(token_file, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, "w", encoding="ascii") as file:
            file.write(token + "\n")
    if not re.fullmatch(r"[A-Za-z0-9_\-]{32,200}", token):
        raise SnapshotError("The existing local token has an invalid format; it was not replaced.")
    token_hash = hashlib.sha256(token.encode()).hexdigest()
    if server_config is not None:
        source = server_config.read_text(encoding="utf-8")
        replacement = f"    'token_sha256' => '{token_hash}',"
        updated, count = re.subn(r"^    'token_sha256' => '[a-f0-9]{64}',$", replacement, source, flags=re.MULTILINE)
        if count != 1:
            raise SnapshotError("Could not identify the reporting verifier in the server config; no source change was made.")
        if updated != source:
            temporary = server_config.with_suffix(".php.prepared")
            temporary.write_text(updated, encoding="utf-8", newline="\n")
            os.replace(temporary, server_config)
    write_json(home / "config.json", {"origin": origin, "token_file": str(token_file.resolve())})
    if not (home / "exclusions.json").exists():
        write_json(home / "exclusions.json", {})
    (home / "activation.json").unlink(missing_ok=True)  # Retire the obsolete manual-activation artifact.
    return {"home": str(home.resolve()), "token_reused": reused,
            "server_verifier_prepared": server_config is not None}


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        raise SnapshotError("Redirect rejected; use the server's canonical HTTPS origin.")


def make_index(path):
    db = sqlite3.connect(path)
    db.execute("CREATE TABLE records(dataset TEXT NOT NULL, record_key TEXT NOT NULL, data TEXT NOT NULL, PRIMARY KEY(dataset,record_key))")
    for key in LINK_KEYS:
        db.execute(f"CREATE INDEX link_{key} ON records(dataset,json_extract(data,'$.{key}'))")
    return db


def parse_json(line):
    def pairs(values):
        result = {}
        for key, value in values:
            if key in result:
                raise SnapshotError("Duplicate JSON key in export.")
            result[key] = value
        return result
    try:
        return json.loads(line, object_pairs_hook=pairs,
                          parse_constant=lambda _: (_ for _ in ()).throw(SnapshotError("Invalid numeric value.")))
    except (ValueError, UnicodeError) as exc:
        raise SnapshotError("Invalid JSON in export.") from exc


def ingest(stream, home: Path, *, max_bytes=10 * 1024**3):
    """Consume exact wire bytes. Only complete validated snapshots can become latest."""
    snapshots = home / "snapshots"
    snapshots.mkdir(parents=True, exist_ok=True, mode=0o700)
    staging = Path(tempfile.mkdtemp(prefix=".partial-", dir=snapshots))
    db = None
    output = None
    try:
        global_hash = hashlib.sha256()
        manifest = None
        complete = None
        definitions = {}
        datasets = []
        position = 0
        count = 0
        table_hash = hashlib.sha256()
        ends = {}
        file_hashes = {}
        total = 0
        for line in iter(lambda: stream.readline(MAX_LINE + 1), b""):
            total += len(line)
            if len(line) > MAX_LINE or total > max_bytes or not line.endswith(b"\n"):
                raise SnapshotError("Export exceeds limits or ends in a partial line.")
            frame = parse_json(line)
            if not isinstance(frame, dict) or complete is not None:
                raise SnapshotError("Unexpected data after completion or invalid frame.")
            kind = frame.get("type")
            if kind == "manifest" and manifest is None:
                if frame.get("schema_version") != VERSION:
                    raise SnapshotError("Unsupported export schema version.")
                if not re.fullmatch(r"[a-f0-9-]{36}", frame.get("snapshot_id", "")):
                    raise SnapshotError("Invalid snapshot identity.")
                definitions = frame.get("datasets")
                if not isinstance(definitions, dict) or not definitions:
                    raise SnapshotError("Missing dataset definitions.")
                for name, definition in definitions.items():
                    if not NAME.fullmatch(name) or not isinstance(definition, dict):
                        raise SnapshotError("Unsafe dataset name or invalid definition.")
                    fields, keys = definition.get("fields"), definition.get("key")
                    if not isinstance(fields, list) or not isinstance(keys, list) or not keys:
                        raise SnapshotError("Missing field or key definition.")
                    if any(not isinstance(f, str) or not NAME.fullmatch(f) for f in fields + keys):
                        raise SnapshotError("Unsafe column name.")
                    if len(fields) != len(set(fields)) or not isinstance(definition.get("available"), bool):
                        raise SnapshotError("Invalid dataset fields or availability.")
                    if definition["available"] and not set(keys) <= set(fields):
                        raise SnapshotError("Dataset identity is unavailable.")
                manifest = frame
                datasets = list(definitions)
                db = make_index(staging / "index.sqlite")
            elif kind == "record" and manifest is not None:
                if position >= len(datasets) or frame.get("dataset") != datasets[position]:
                    raise SnapshotError("Out-of-order or unexpected dataset.")
                name = datasets[position]
                definition = definitions[name]
                data = frame.get("data")
                if not definition["available"] or not isinstance(data, dict) or set(data) != set(definition["fields"]):
                    raise SnapshotError("Record does not match the export contract.")
                key = [data[k] for k in definition["key"]]
                if any(not isinstance(k, (int, str)) or isinstance(k, bool) or str(k) == "" for k in key):
                    raise SnapshotError("Invalid record identity.")
                payload = dumps(data)
                db.execute("INSERT INTO records VALUES(?,?,?)", (name, dumps([str(k) for k in key]), payload))
                if output is None:
                    output = (staging / f"{name}.jsonl").open("wb")
                output.write((payload + "\n").encode("utf-8"))
                table_hash.update(line)
                count += 1
            elif kind == "dataset_end" and manifest is not None:
                if position >= len(datasets) or frame.get("dataset") != datasets[position]:
                    raise SnapshotError("Unexpected or duplicate dataset completion.")
                name = datasets[position]
                if frame.get("count") != count or frame.get("sha256") != table_hash.hexdigest():
                    raise SnapshotError("Dataset count/checksum mismatch.")
                if output is not None:
                    output.close()
                    output = None
                else:
                    (staging / f"{name}.jsonl").touch(mode=0o600)
                file_hashes[name] = digest(staging / f"{name}.jsonl")
                ends[name] = frame
                position += 1
                count = 0
                table_hash = hashlib.sha256()
            elif kind == "complete" and manifest is not None:
                if position != len(datasets) or frame.get("snapshot_id") != manifest["snapshot_id"]:
                    raise SnapshotError("Export completion is missing datasets or has the wrong identity.")
                if frame.get("counts") != {n: e["count"] for n, e in ends.items()} or frame.get("sha256") != global_hash.hexdigest():
                    raise SnapshotError("Whole-export count/checksum mismatch.")
                complete = frame
            else:
                raise SnapshotError("Export failed or contains an unexpected frame.")
            if kind != "complete":
                global_hash.update(line)
        if complete is None:
            raise SnapshotError("Export is incomplete; the previous snapshot is unchanged.")
        db.commit()
        db.close()
        db = None
        metadata = {"manifest": manifest, "complete": complete, "datasets": ends,
                    "file_sha256": file_hashes, "index_sha256": digest(staging / "index.sqlite"),
                    "downloaded_at": datetime.now(timezone.utc).isoformat(), "bytes": total}
        write_json(staging / "snapshot.json", metadata)
        destination = snapshots / manifest["snapshot_id"]
        if destination.exists():
            raise SnapshotError("Snapshot identity already exists; refusing to overwrite it.")
        staging.rename(destination)
        pointer = home / "latest.tmp"
        write_json(pointer, {"snapshot_id": manifest["snapshot_id"]})
        os.replace(pointer, home / "latest.json")
        return {"snapshot": str(destination.resolve()), "counts": complete["counts"], "bytes": total}
    except sqlite3.IntegrityError as exc:
        raise SnapshotError("Duplicate record identity in export.") from exc
    finally:
        if output:
            output.close()
        if db:
            db.close()
        if staging.exists():
            shutil.rmtree(staging)  # Exact private temp directory created above; never a user-supplied target.


def download(home: Path, allow_local=False, timeout=300, max_bytes=10 * 1024**3):
    config = json.loads((home / "config.json").read_text(encoding="utf-8"))
    url = endpoint(config["origin"], allow_local)
    token = Path(config["token_file"]).read_text(encoding="ascii").strip()
    if not re.fullmatch(r"[A-Za-z0-9_\-]{32,200}", token):
        raise SnapshotError("Invalid reporting credential format.")
    private_directory(home)
    lock = home / ".download.lock"
    try:
        lock_fd = os.open(lock, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    except FileExistsError as exc:
        raise SnapshotError("Another download is active. Remove a stale lock only after confirming the process ended.") from exc
    os.close(lock_fd)
    try:
        request = urllib.request.Request(url, headers={"Authorization": f"Bearer {token}",
                     "Accept": "application/x-ndjson", "Accept-Encoding": "identity"}, method="GET")
        opener = urllib.request.build_opener(NoRedirect)
        with opener.open(request, timeout=timeout) as response:
            if response.status != 200 or response.headers.get_content_type() != "application/x-ndjson":
                raise SnapshotError("Server did not return a reporting export.")
            return ingest(response, home, max_bytes=max_bytes)
    except urllib.error.HTTPError as exc:
        raise SnapshotError(f"Reporting server returned HTTP {exc.code}; response body withheld.") from None
    except urllib.error.URLError:
        raise SnapshotError("Reporting server could not be reached over the configured connection.") from None
    finally:
        lock.unlink(missing_ok=True)


def resolve_snapshot(home: Path, selected=None):
    if selected:
        result = Path(selected).resolve()
    else:
        identity = json.loads((home / "latest.json").read_text(encoding="utf-8"))["snapshot_id"]
        if not re.fullmatch(r"[a-f0-9-]{36}", identity):
            raise SnapshotError("Invalid latest snapshot pointer.")
        result = home / "snapshots" / identity
    if not (result / "snapshot.json").is_file():
        raise SnapshotError("No complete snapshot is available; run download after server activation.")
    return result


def verify(snapshot: Path, full=False):
    metadata = json.loads((snapshot / "snapshot.json").read_text(encoding="utf-8"))
    if digest(snapshot / "index.sqlite") != metadata["index_sha256"]:
        raise SnapshotError("Local index checksum mismatch; download a fresh snapshot.")
    if full:
        for name, expected in metadata["file_sha256"].items():
            if not NAME.fullmatch(name) or digest(snapshot / f"{name}.jsonl") != expected:
                raise SnapshotError("Local raw dataset checksum mismatch.")
    return metadata
