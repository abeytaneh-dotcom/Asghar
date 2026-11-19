"""Minimal secure DirectYar webhook and authenticated rule management API.

Run locally behind a TLS reverse proxy with /webhook publicly reachable.
"""
import hashlib
import hmac
import json
import os
import secrets
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlsplit

from engine import Repository, RuleEngine, parse_webhook
from meta_api import MetaAPI

DB_PATH = os.getenv("DIRECTYAR_DB", str(Path(__file__).parent / "directyar.db"))
VERIFY_TOKEN = os.getenv("META_VERIFY_TOKEN", "")
APP_SECRET = os.getenv("META_APP_SECRET", "")
IG_ACCESS_TOKEN = os.getenv("META_IG_ACCESS_TOKEN", "")
IG_USER_ID = os.getenv("META_IG_USER_ID", "")
ADMIN_KEY = os.getenv("DIRECTYAR_ADMIN_KEY", "")
LIVE = os.getenv("DIRECTYAR_LIVE", "0") == "1"
CONFIGURED = all((VERIFY_TOKEN, APP_SECRET, IG_ACCESS_TOKEN, IG_USER_ID, ADMIN_KEY))
if LIVE and not CONFIGURED:
    raise SystemExit("Refusing to enable live mode without all credentials")
REPO = Repository(DB_PATH)
ENGINE = RuleEngine(REPO, MetaAPI(IG_ACCESS_TOKEN, IG_USER_ID), IG_USER_ID, live=LIVE and CONFIGURED)


class Handler(BaseHTTPRequestHandler):
    server_version = "DirectYar/1.3"
    def log_message(self, fmt, *args):
        # Avoid URL query strings and credentials in logs.
        return

    def output(self, status, obj):
        raw = json.dumps(obj, ensure_ascii=False).encode("utf8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(raw)))
        self.send_header("Cache-Control", "no-store")
        self.send_header("X-Content-Type-Options", "nosniff")
        self.end_headers()
        self.wfile.write(raw)

    def authorized(self):
        return bool(ADMIN_KEY) and hmac.compare_digest(self.headers.get("Authorization", ""), "Bearer " + ADMIN_KEY)

    def read_body(self):
        try: size = int(self.headers.get("Content-Length", "0"))
        except ValueError: return None
        if not 0 < size <= 1024 * 1024: return None
        return self.rfile.read(size)

    def do_GET(self):
        u = urlsplit(self.path)
        if u.path == "/health":
            return self.output(200, {"healthy": True, "live": ENGINE.live, "configured": CONFIGURED})
        if u.path == "/webhook":
            q = parse_qs(u.query)
            if not VERIFY_TOKEN or q.get("hub.mode", [""])[0] != "subscribe" or not secrets.compare_digest(q.get("hub.verify_token", [""])[0], VERIFY_TOKEN):
                return self.output(403, {"error": "verification failed"})
            challenge = q.get("hub.challenge", [""])[0]
            raw = challenge.encode("utf8")
            self.send_response(200)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.send_header("Content-Length", str(len(raw)))
            self.end_headers()
            self.wfile.write(raw)
            return
        if u.path.startswith("/api/"):
            if not self.authorized(): return self.output(401, {"error":"unauthorized"})
            if u.path == "/api/rules": return self.output(200, {"rules": REPO.get_rules()})
            if u.path == "/api/events": return self.output(200, {"events": REPO.history()})
            if u.path == "/api/status": return self.output(200, {"live": ENGINE.live, "configured": CONFIGURED, "rule_count": len(REPO.get_rules())})
        return self.output(404, {"error": "not found"})

    def do_PUT(self):
        if urlsplit(self.path).path != "/api/rules": return self.output(404, {"error": "not found"})
        if not self.authorized(): return self.output(401, {"error": "unauthorized"})
        raw = self.read_body()
        if raw is None: return self.output(413, {"error":"invalid payload length"})
        try:
            body = json.loads(raw)
            REPO.put_rules(body["rules"])
        except (ValueError, TypeError, KeyError, json.JSONDecodeError) as e:
            return self.output(400, {"error": str(e)})
        return self.output(200, {"saved": True, "rule_count": len(REPO.get_rules())})

    def do_POST(self):
        if urlsplit(self.path).path != "/webhook": return self.output(404, {"error": "not found"})
        raw = self.read_body()
        if raw is None: return self.output(413, {"error":"payload too large"})
        if not APP_SECRET: return self.output(503, {"error":"not configured"})
        digest = "sha256=" + hmac.new(APP_SECRET.encode(), raw, hashlib.sha256).hexdigest()
        if not hmac.compare_digest(digest, self.headers.get("X-Hub-Signature-256", "")):
            return self.output(403, {"error":"invalid signature"})
        try:
            data = json.loads(raw)
            events = parse_webhook(data)
        except (ValueError, TypeError, AttributeError):
            return self.output(400, {"error":"invalid event"})
        # Return promptly to Meta. Reserve keys synchronously inside engine to prevent duplicates.
        def worker():
            for event in events:
                try:
                    if event["type"] == "comment": ENGINE.process_comment(event)
                    if event["type"] == "dm": ENGINE.process_dm(event)
                except Exception:
                    # No message bodies, bearer tokens or user IDs in server logs.
                    pass
        threading.Thread(target=worker, daemon=True).start()
        return self.output(200, {"received": len(events)})


def main():
    host = os.getenv("DIRECTYAR_BIND", "127.0.0.1")
    port = int(os.getenv("DIRECTYAR_PORT", "8080"))
    print("DirectYar listening", host, port, "live", ENGINE.live)
    ThreadingHTTPServer((host, port), Handler).serve_forever()

if __name__ == "__main__": main()
