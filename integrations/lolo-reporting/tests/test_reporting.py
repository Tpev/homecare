import hashlib
import io
import json
import os
from pathlib import Path
import sys
import tempfile
import threading
import unittest
import uuid
from http.server import BaseHTTPRequestHandler, HTTPServer

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from downloader import NoRedirect, SnapshotError, download, endpoint, ingest, initialize, resolve_snapshot, verify, write_json
from reports import Reporting, Snapshot, caregiver_evidence, changes, evidence


def wire(tables, snapshot_id=None, absent=()):
    definitions = {}
    for table, rows in tables.items():
        fields = sorted(set().union(*(r.keys() for r in rows))) if rows else ["id"]
        definitions[table] = {"available": table not in absent, "fields": fields, "key": ["id"], "json_fields": [], "missing_fields": [], "utc_fields": []}
    frames = [{"type": "manifest", "schema_version": 1, "snapshot_id": snapshot_id or str(uuid.uuid4()),
               "started_at": "2026-11-26T12:00:00Z", "database_timezone": "UTC", "datasets": definitions}]
    encode = lambda value: (json.dumps(value, separators=(",", ":")) + "\n").encode()
    counts = {}
    for table, rows in tables.items():
        record_lines = []
        for row in rows:
            record = {"type": "record", "dataset": table, "data": {f: row.get(f) for f in definitions[table]["fields"]}}
            frames.append(record)
            record_lines.append(encode(record))
        counts[table] = len(rows)
        frames.append({"type": "dataset_end", "dataset": table, "count": len(rows), "sha256": hashlib.sha256(b"".join(record_lines)).hexdigest()})
    body = b"".join(encode(frame) for frame in frames)
    return body + encode({"type": "complete", "snapshot_id": frames[0]["snapshot_id"], "counts": counts, "sha256": hashlib.sha256(body).hexdigest(), "finished_at": "2026-11-26T12:01:00Z"})


def fixtures():
    def lead(key, stage, family, **extra):
        return dict(id=key, name=f"Customer {key}", status=stage, lead_type="family", family_account_id=family,
                    created_at="2026-11-22 10:00:00", submitted_at="2026-11-22 10:00:00",
                    first_call_at=None, first_connected_at=None, next_follow_up_at=None, assigned_admin_id=None,
                    source="facebook", **extra)
    def request(key, **extra):
        return dict(id=key, family_account_id=key, status="open", is_system_generated=False,
                    staff_archived_at=None, created_at="2026-11-22 11:00:00", **extra)
    def booking(key, request_id, status, **extra):
        return dict(id=key, care_request_id=request_id, family_account_id=request_id, status=status,
                    replacement_released_at=None, cancelled_at=None, scheduled_end_at="2026-11-27 16:00:00", **extra)
    return {
        "users": [{"id": 5, "name": "Staff", "role": "admin", "access_revoked_at": None, "created_at": "2026-01-01 10:00:00"}],
        "leads": [lead(1, "qualified", 1), lead(2, "converted", 2), lead(3, "nurture", 3, converted_at="2026-11-23 12:00:00"),
                  lead(4, "qualified", 4, do_not_contact_at="2026-11-24 10:00:00"), lead(5, "qualified", 1)],
        "lead_activities": [{"id": 1, "lead_id": 1, "type": "note", "body": "Customer wants Saturday; price still needs confirmation.", "occurred_at": "2026-11-25 10:00:00", "metadata": {}},
                            {"id": 2, "lead_id": 3, "type": "note", "body": "Correction: no care has been used yet.", "occurred_at": "2026-11-25 11:00:00", "metadata": {}},
                            {"id": 3, "lead_id": 1, "type": "note", "body": "Merged record", "metadata": {"merged_lead_id": 99}, "occurred_at": "2026-11-24 11:00:00"}],
        "sms_messages": [{"id": 1, "lead_id": 1, "direction": "inbound", "received_at": "2026-11-25 09:00:00", "body": "Can we start Saturday?"},
                         {"id": 2, "lead_id": 4, "direction": "inbound", "received_at": "2026-11-25 09:00:00", "body": "Stop contacting me"}],
        "care_requests": [request(1), request(2), request(3), dict(request(4), is_system_generated=True), request(5)],
        "care_bookings": [booking(1, 1, "cancelled", completed_at=None), booking(2, 2, "scheduled", completed_at=None),
                          booking(3, 3, "completed", completed_at="2026-11-10 10:00:00", worked_minutes=120),
                          booking(5, 5, "completed", completed_at="2026-11-25 10:00:00", worked_minutes=60)],
        "care_request_applications": [{"id": 1, "care_request_id": 1, "status": "shortlisted"}],
        "care_plans": [], "customer_care_actions": [{"id": 1, "care_request_id": 1, "actor_user_id": 5, "note": "Finding weekend coverage", "created_at": "2026-11-25 12:00:00"}],
        "family_accounts": [{"id": 1, "owner_user_id": 11}],
        "care_request_conversations": [{"id": 8, "care_request_id": 1}],
        "care_request_messages": [{"id": 1, "care_request_conversation_id": 8, "sender_user_id": 5, "body": "Is Saturday available?", "created_at": "2026-11-25 10:00:00"}],
        "caregiver_profiles": [], "care_booking_payments": [], "care_hour_purchases": [],
    }


class TransportTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.home = Path(self.tmp.name)

    def tearDown(self):
        self.tmp.cleanup()

    def test_valid_export_has_atomic_pointer_index_and_raw_records(self):
        result = ingest(io.BytesIO(wire({"leads": [{"id": 1, "body": "Complete multi-line\nnote"}]})), self.home)
        path = resolve_snapshot(self.home)
        self.assertEqual(path.resolve(), Path(result["snapshot"]))
        metadata = verify(path, full=True)
        self.assertEqual(1, metadata["complete"]["counts"]["leads"])
        with (path / "leads.jsonl").open() as stream:
            self.assertEqual("Complete multi-line\nnote", json.loads(stream.readline())["body"])

    def test_token_is_created_once_and_only_its_hash_is_prepared_for_deployment(self):
        server_config = self.home / "reporting.php"
        server_config.write_text("<?php\nreturn [\n    'token_sha256' => '" + "0" * 64 + "',\n];\n")
        first = initialize(self.home, "https://carelolo.com", server_config)
        token = (self.home / "token").read_text().strip()
        expected_hash = hashlib.sha256(token.encode()).hexdigest()
        self.assertFalse(first["token_reused"])
        self.assertTrue(first["server_verifier_prepared"])
        self.assertIn(expected_hash, server_config.read_text())
        self.assertNotIn(token, server_config.read_text())
        write_json(self.home / "exclusions.json", {"leads": {"1": "Keep this local exclusion"}})
        second = initialize(self.home, "https://carelolo.com", server_config)
        self.assertTrue(second["token_reused"])
        self.assertEqual(token, (self.home / "token").read_text().strip())
        self.assertEqual({"leads": {"1": "Keep this local exclusion"}}, json.loads((self.home / "exclusions.json").read_text()))
        self.assertNotIn(token, json.dumps([first, second]))
        self.assertFalse((self.home / "activation.json").exists())

    def test_truncation_corruption_and_extra_frames_never_replace_latest(self):
        original = wire({"leads": [{"id": 1, "status": "qualified"}]})
        ingest(io.BytesIO(original), self.home)
        latest = (self.home / "latest.json").read_bytes()
        for damaged in (original[:-30], original.replace(b'"qualified"', b'"converted"'), original + b'{}\n'):
            with self.subTest(), self.assertRaises(SnapshotError):
                ingest(io.BytesIO(damaged), self.home)
            self.assertEqual(latest, (self.home / "latest.json").read_bytes())
        self.assertFalse(list((self.home / "snapshots").glob(".partial-*")))

    def test_duplicate_record_and_unsafe_dataset_are_rejected(self):
        for tables in ({"leads": [{"id": 1}, {"id": 1}]}, {"../escape": [{"id": 1}]}):
            with self.assertRaises(SnapshotError):
                ingest(io.BytesIO(wire(tables)), self.home)
        self.assertFalse((self.home / "latest.json").exists())

    def test_unknown_unavailable_data_and_size_limit_are_enforced(self):
        with self.assertRaises(SnapshotError):
            ingest(io.BytesIO(wire({"leads": [{"id": 1}]}, absent=["leads"])), self.home)
        with self.assertRaises(SnapshotError):
            ingest(io.BytesIO(wire({"leads": [{"id": 1}]})), self.home, max_bytes=100)

    def test_no_redirects_or_credentials_in_origin(self):
        for origin in ("http://carelolo.com", "https://user:secret@carelolo.com", "https://carelolo.com?token=x", "https://carelolo.com/path"):
            with self.assertRaises(SnapshotError):
                endpoint(origin)
        self.assertEqual("http://127.0.0.1:8000/api/reporting/v1/export", endpoint("http://127.0.0.1:8000", True))
        with self.assertRaises(SnapshotError):
            NoRedirect().redirect_request(None, None, 302, None, None, "https://other.test")

    def test_local_index_corruption_is_detected(self):
        ingest(io.BytesIO(wire({"leads": [{"id": 1}]})), self.home)
        path = resolve_snapshot(self.home)
        with (path / "index.sqlite").open("ab") as file:
            file.write(b"corruption")
        with self.assertRaises(SnapshotError):
            verify(path)

    def test_http_download_uses_get_and_redirect_never_forwards_token(self):
        seen = []
        data = wire({"leads": [{"id": 1}]})
        class Handler(BaseHTTPRequestHandler):
            redirect = False
            def do_GET(self):
                seen.append((self.command, self.path, self.headers.get("Authorization")))
                if self.redirect:
                    self.send_response(302)
                    self.send_header("Location", "/unexpected")
                    self.end_headers()
                else:
                    self.send_response(200)
                    self.send_header("Content-Type", "application/x-ndjson")
                    self.end_headers()
                    self.wfile.write(data)
            def log_message(self, *args):
                pass
        server = HTTPServer(("127.0.0.1", 0), Handler)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        try:
            token = "synthetic_" + "x" * 40
            (self.home / "token").write_text(token)
            write_json(self.home / "config.json", {"origin": f"http://127.0.0.1:{server.server_port}", "token_file": str(self.home / "token")})
            download(self.home, allow_local=True)
            self.assertEqual([("GET", "/api/reporting/v1/export", "Bearer " + token)], seen)
            Handler.redirect = True
            with self.assertRaises(SnapshotError):
                download(self.home, allow_local=True)
            self.assertEqual(2, len(seen))
            self.assertFalse((self.home / ".download.lock").exists())
        finally:
            server.shutdown()
            thread.join()
            server.server_close()


class ReportTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.home = Path(self.tmp.name)
        ingest(io.BytesIO(wire(fixtures())), self.home)
        self.snapshot = Snapshot(resolve_snapshot(self.home))

    def tearDown(self):
        self.snapshot.close()
        self.tmp.cleanup()

    # Only business-specific tests here; transport tests belong to TransportTests.
    def test_hot_candidates_require_evidence_and_respect_do_not_contact(self):
        report = Reporting(self.snapshot).report("hot")
        self.assertEqual([1], [row["lead_id"] for row in report["traction_candidates"]])
        self.assertEqual("needs_note_review", report["traction_candidates"][0]["assessment"])
        self.assertTrue(report["traction_candidates"][0]["signals"])

    def test_matching_ignores_generated_and_current_placement_but_keeps_cancelled_and_past_care(self):
        queue = Reporting(self.snapshot).searches()
        self.assertEqual({1, 3, 5}, {row["request_id"] for row in queue})
        self.assertEqual("shortlisting", next(row for row in queue if row["request_id"] == 1)["state"])

    def test_recorded_conversion_is_not_delivered_care_and_old_conversion_is_flagged(self):
        questions = {row["lead_id"]: row["questions"] for row in Reporting(self.snapshot).quality()}
        self.assertTrue(any("What does converted mean" in question for question in questions[2]))
        self.assertTrue(any("conversion date remains" in question for question in questions[3]))
        self.assertTrue(any("existing customer" in question for question in questions[3]))

    def test_cohort_deduplicates_households_and_separates_existing_care(self):
        metrics = Reporting(self.snapshot).kpis()
        self.assertEqual(4, metrics["linked_cohort"]["distinct_households"])
        self.assertEqual(3, metrics["new_customer_cohort"]["eligible_linked_households"])
        self.assertEqual(1, metrics["new_customer_cohort"]["repeat_or_preexisting_households_excluded"])
        self.assertEqual(0, metrics["new_customer_cohort"]["households_with_first_completed_care"])
        self.assertIsNone(metrics["booking_payment_balances_as_of_snapshot"]["value"])

    def test_exact_exclusions_propagate_to_household_and_bookings(self):
        reporter = Reporting(self.snapshot, exclusions={"family_accounts": {"1": "Synthetic test household"}})
        self.assertEqual({2, 3, 4}, {lead["id"] for lead in reporter.family_leads})
        self.assertFalse(any(row.get("family_account_id") == 1 for row in reporter.searches()))

    def test_full_evidence_includes_customer_care_messages_and_merge_alias(self):
        packet = evidence(self.snapshot, 99)
        self.assertEqual(1, packet["canonical_lead_id"])
        self.assertEqual("Finding weekend coverage", packet["records"]["customer_care_actions"][0]["note"])
        self.assertEqual("Is Saturday available?", packet["records"]["care_request_messages"][0]["body"])
        self.assertTrue(any(row["evidence"] == "lead_activities:1" for row in packet["chronology"]))

    def test_report_limits_are_explicit_and_missing_queue_data_is_unknown(self):
        report = Reporting(self.snapshot).report("status", limit=1)
        self.assertEqual(1, len(report["caregiver_searches"]))
        self.assertEqual(3, report["list_counts"]["caregiver_searches"]["total"])
        self.assertFalse(report["queue_coverage"]["caregiver_searches"]["complete"])

    def test_diff_distinguishes_changed_missing_and_added_without_guessing_loss(self):
        updated = fixtures()
        updated["leads"][0]["status"] = "contacted"
        updated["leads"] = updated["leads"][:-1]
        ingest(io.BytesIO(wire(updated)), self.home)
        later = Snapshot(resolve_snapshot(self.home))
        try:
            diff = changes(later, self.snapshot)["datasets"]["leads"]
            self.assertIn(["5"], diff["no_longer_present"])
            self.assertIn({"key": ["1"], "fields": ["status"]}, diff["changed"])
        finally:
            later.close()

    def test_request_evidence_works_without_a_linked_lead(self):
        packet = evidence(self.snapshot, request_id=5)
        self.assertEqual(5, packet["requested_request_id"])
        self.assertIsNone(packet["canonical_lead_id"])
        self.assertEqual([5], [b["id"] for b in packet["records"]["care_bookings"]])

    def test_repeat_submission_does_not_turn_an_old_household_into_new_acquisition(self):
        reporter = Reporting(self.snapshot)
        for lead in reporter.family_leads:
            if lead["family_account_id"] == 1:
                lead["created_at"] = "2026-10-01 10:00:00"
        cohort = reporter.kpis()["new_customer_cohort"]
        self.assertEqual(2, cohort["eligible_linked_households"])
        self.assertEqual(2, cohort["repeat_or_preexisting_households_excluded"])

    def test_caregiver_evidence_includes_notes_and_retained_activity(self):
        tables = {"users": [{"id": 50, "role": "caregiver", "name": "Synthetic caregiver"}],
                  "caregiver_profiles": [{"id": 9, "user_id": 50, "status": "approved"}],
                  "caregiver_crm_records": [{"id": 1, "caregiver_user_id": 50, "notes": "Prefers weekends"}],
                  "caregiver_crm_activities": [{"id": 1, "caregiver_user_id": 50, "body": "Availability confirmed", "occurred_at": "2026-11-25 10:00:00"}]}
        ingest(io.BytesIO(wire(tables)), self.home)
        later = Snapshot(resolve_snapshot(self.home))
        try:
            packet = caregiver_evidence(later, 50)
            self.assertEqual("Prefers weekends", packet["records"]["caregiver_crm_records"][0]["notes"])
            self.assertEqual("Availability confirmed", packet["records"]["caregiver_crm_activities"][0]["body"])
        finally:
            later.close()


class PhpInteropTest(unittest.TestCase):
    @unittest.skipUnless(os.getenv("LOLO_REPORTING_TEST_WIRE"), "Set LOLO_REPORTING_TEST_WIRE to a PHP test export for cross-language validation.")
    def test_php_export_is_accepted_without_reencoding_checksums(self):
        with tempfile.TemporaryDirectory() as directory:
            with Path(os.environ["LOLO_REPORTING_TEST_WIRE"]).open("rb") as stream:
                ingest(stream, Path(directory))
            snapshot = Snapshot(resolve_snapshot(Path(directory)))
            try:
                packet = evidence(snapshot, 900001)
                self.assertEqual(45, len(packet["records"]["lead_activities"]))
                self.assertNotIn("SECRET_PATH", json.dumps(packet))
            finally:
                snapshot.close()


if __name__ == "__main__":
    unittest.main()
