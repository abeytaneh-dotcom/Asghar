"""DirectYar rule engine: official Instagram webhook -> conditional, verified replies.

No browser scraping, password collection, or follower-status guessing.
"""
from __future__ import annotations
import json
import re
import sqlite3
import threading
import time
import unicodedata
from typing import Any

FOLLOW_BUTTON = "فالو کردم ✅"
FOLLOW_PROMPT = (
    "دوست عزیزم 🌷 لطفاً اول پیج ما رو فالو کن تا بتونم محتوای درخواستی رو برات بفرستم. "
    "بعد از فالو، دکمه «فالو کردم ✅» رو بزن تا دوباره بررسی کنم."
)
# Comment -> DM follow button. The user must NOT retype the keyword after commenting.
# Pressing FOLLOW_BUTTON starts a verified check against Meta's follow-status field.
PRIVATE_PROMPT = FOLLOW_PROMPT
DEFAULT_COMMENT = "دایرکت براتون ارسال شد ✅"


def normalize(text: str) -> str:
    text = unicodedata.normalize("NFKC", text).lower().replace("ي", "ی").replace("ك", "ک")
    text = re.sub(r"[\u064b-\u065f\u0670\u200c]", "", text)
    return " ".join(text.split())


def matches(rule: dict, text: str) -> bool:
    keyword = normalize(rule.get("keyword", ""))
    return bool(keyword) and keyword in normalize(text)


def validate_rules(rules: Any) -> list[dict]:
    if not isinstance(rules, list) or len(rules) > 100:
        raise ValueError("rules must be a list with max 100 entries")
    ids: set[str] = set()
    clean: list[dict] = []
    for rule in rules:
        if not isinstance(rule, dict):
            raise ValueError("invalid rule")
        ident = str(rule.get("id", "")).strip()
        keyword = str(rule.get("keyword", "")).strip()
        reply = str(rule.get("reply", "")).strip()
        post_id = str(rule.get("post_id", "")).strip()
        comment_reply = str(rule.get("comment_public_reply", DEFAULT_COMMENT)).strip()
        if not re.fullmatch(r"[a-zA-Z0-9_-]{1,70}", ident) or ident in ids:
            raise ValueError("rule IDs must be distinct and safe")
        if not 1 <= len(keyword) <= 100 or not 1 <= len(reply) <= 1900 or len(comment_reply) > 500:
            raise ValueError("keyword/reply/comment text length invalid")
        if post_id and not re.fullmatch(r"[0-9]{1,40}", post_id):
            raise ValueError("post_id must be digits or empty")
        ids.add(ident)
        clean.append({"id": ident, "keyword": keyword, "reply": reply,
                      "post_id": post_id, "comment_enabled": rule.get("comment_enabled", True) is True,
                      "dm_enabled": rule.get("dm_enabled", True) is True,
                      "follow_required": rule.get("follow_required", True) is True,
                      "comment_public_reply": comment_reply or DEFAULT_COMMENT})
    return clean


class Repository:
    def __init__(self, path: str):
        self.db = sqlite3.connect(path, check_same_thread=False, isolation_level=None)
        self.lock = threading.RLock()
        with self.lock:
            self.db.execute("PRAGMA journal_mode=WAL")
            self.db.executescript("""
            CREATE TABLE IF NOT EXISTS rules (id TEXT PRIMARY KEY, data TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS events (key TEXT PRIMARY KEY, kind TEXT NOT NULL,
                status TEXT NOT NULL, detail TEXT DEFAULT '', created_at INTEGER NOT NULL);
            CREATE TABLE IF NOT EXISTS pending (user_id TEXT PRIMARY KEY, rule_id TEXT NOT NULL,
                updated_at INTEGER NOT NULL);
            """)

    def get_rules(self) -> list[dict]:
        with self.lock:
            return [json.loads(r[0]) for r in self.db.execute("SELECT data FROM rules ORDER BY rowid").fetchall()]

    def put_rules(self, rules: list[dict]):
        rules = validate_rules(rules)
        with self.lock:
            self.db.execute("BEGIN IMMEDIATE")
            try:
                self.db.execute("DELETE FROM rules")
                self.db.executemany("INSERT INTO rules VALUES (?, ?)",
                                    [(r["id"], json.dumps(r, ensure_ascii=False)) for r in rules])
                self.db.execute("COMMIT")
            except Exception:
                self.db.execute("ROLLBACK")
                raise

    def reserve(self, key: str, kind: str) -> bool:
        with self.lock:
            result = self.db.execute("INSERT OR IGNORE INTO events VALUES (?, ?, ?, ?, ?)",
                                     (key, kind, "reserved", "", int(time.time())))
            return result.rowcount > 0

    def finish(self, key: str, status: str, detail: str = ""):
        with self.lock:
            self.db.execute("UPDATE events SET status=?,detail=? WHERE key=?", (status, detail[:600], key))

    def set_pending(self, user: str, rule: str):
        with self.lock:
            self.db.execute("INSERT OR REPLACE INTO pending VALUES (?, ?, ?)", (user, rule, int(time.time())))

    def get_pending(self, user: str) -> str | None:
        with self.lock:
            item = self.db.execute("SELECT rule_id FROM pending WHERE user_id=?", (user,)).fetchone()
            return item[0] if item else None

    def clear_pending(self, user: str):
        with self.lock:
            self.db.execute("DELETE FROM pending WHERE user_id=?", (user,))

    def history(self, limit=40):
        with self.lock:
            return [dict(zip(("key","kind","status","detail","created_at"), r)) for r in
                    self.db.execute("SELECT key,kind,status,detail,created_at FROM events ORDER BY created_at DESC LIMIT ?", (limit,))]


class RuleEngine:
    def __init__(self, repo: Repository, provider, account_id: str, live: bool = False):
        self.repo, self.provider, self.account_id, self.live = repo, provider, account_id, live
        # Serialise each webhook action so two simultaneous taps cannot send twice.
        self._processing_lock = threading.RLock()

    def _eligible(self, event):
        # Restrict webhook events to the configured business account.
        return str(event.get("business_id", "")) == self.account_id

    def _send(self, mode, *args, **kwargs):
        if not self.live:
            return {"dry_run": True, "message_id": "SIMULATED"}
        if mode == "dm": return self.provider.dm(*args, **kwargs)
        if mode == "private": return self.provider.private_reply(*args, **kwargs)
        if mode == "public": return self.provider.public_reply(*args, **kwargs)
        raise ValueError("unknown mode")

    def process_comment(self, event: dict) -> str:
        with self._processing_lock:
            if not self._eligible(event): return "ignored_account"
            comment = str(event.get("comment_id", ""))
            media = str(event.get("media_id", ""))
            content = str(event.get("text", ""))
            user = str(event.get("user_id", ""))
            if not comment or not media or not user: return "ignored_missing_id"
            if user == self.account_id or event.get("is_self_reply"): return "ignored_self"
            # Reject stale comments: private replies have a limited eligibility window.
            created_at = event.get("created_at")
            if created_at:
                try:
                    t = int(created_at)
                    if t > 10**12: t //= 1000
                    if time.time() - t > 7*24*60*60: return "comment_too_old"
                except (TypeError, ValueError): return "invalid_timestamp"
            rules = self.repo.get_rules()
            rule = next((r for r in rules if r["comment_enabled"] and
                         (not r["post_id"] or r["post_id"] == media) and matches(r, content)), None)
            if not rule: return "no_rule"
            key = "comment:" + comment
            if not self.repo.reserve(key, "comment"): return "duplicate"
            if not self.live:
                self.repo.finish(key, "simulated", "would send private follow-check button")
                return "simulated"
            try:
                # Send the follow-check button as the ONE permitted private reply.
                # The signed webhook reply then identifies the user and pending rule.
                quick = "FOLLOW_CHECK:" + rule["id"]
                initial = self._send("private", comment, PRIVATE_PROMPT, quick)
                if not initial.get("message_id") and not initial.get("id"):
                    raise RuntimeError("Meta did not confirm private reply")
                self.repo.set_pending(user, rule["id"])
                self.repo.finish(key, "private_sent", "private follow-check message confirmed")
            except Exception as exc:
                self.repo.finish(key, "error", type(exc).__name__)
                return "error"
            # Public comment is not required to maintain pending state.
            # If it fails, do not re-send the private reply on a retry.
            try:
                public = self._send("public", comment, rule["comment_public_reply"])
                if public.get("id") or public.get("message_id"):
                    self.repo.finish(key, "completed", "private + public replies confirmed")
                    return "completed"
                self.repo.finish(key, "private_sent", "public reply not confirmed")
                return "private_sent"
            except Exception as exc:
                self.repo.finish(key, "private_sent", "public reply failed: " + type(exc).__name__)
                return "private_sent"

    def process_dm(self, event: dict) -> str:
        with self._processing_lock:
            if not self._eligible(event): return "ignored_account"
            user = str(event.get("user_id", ""))
            text = str(event.get("text", ""))
            payload = str(event.get("payload", ""))
            msg_id = str(event.get("message_id", ""))
            if not msg_id or not user: return "ignored_missing_id"
            if user == self.account_id or event.get("is_echo"): return "ignored_self"
            timestamp_ms = event.get("timestamp_ms")
            if timestamp_ms:
                try:
                    age_ms = time.time() * 1000 - int(timestamp_ms)
                    if age_ms > 24 * 3600 * 1000: return "outside_messaging_window"
                except (ValueError, TypeError): return "invalid_timestamp"
            rules = self.repo.get_rules()
            pending = self.repo.get_pending(user)
            pressed = payload.startswith("FOLLOW_CHECK:") or normalize(text) in (normalize(FOLLOW_BUTTON), normalize("فالو کردم"))
            if pressed:
                rule_id = payload.split(":", 1)[1] if payload.startswith("FOLLOW_CHECK:") else pending
                # Never let a forged/unrelated button trigger delivery.
                rule = next((r for r in rules if r["id"] == rule_id and r["id"] == pending), None)
            else:
                rule = next((r for r in rules if r["dm_enabled"] and matches(r, text)), None)
            if not rule: return "no_rule"
            event_key = "dm:" + msg_id
            if not self.repo.reserve(event_key, "dm"): return "duplicate"
            if not self.live:
                self.repo.finish(event_key, "simulated", "would request Meta follow status")
                return "simulated"
            try:
                if rule["follow_required"]:
                    state = self.provider.follow_status(user)
                    if state not in (True, False) or type(state) is not bool:
                        # Unknown must NEVER grant access. A button may be reissued
                        # for retry, but record status as unknown, not a confirmed nonfollow.
                        self._send("dm", user, "هنوز نتونستم وضعیت فالو رو از اینستاگرام تأیید کنم. لطفاً یک بار دیگر روی «فالو کردم» بزن.", "FOLLOW_CHECK:" + rule["id"])
                        self.repo.set_pending(user, rule["id"])
                        self.repo.finish(event_key, "unverified", "Meta follow status unavailable; retry offered")
                        return "unverified"
                    if state is False:
                        # Repeat exact text and quick-reply button on EVERY failed tap.
                        self._send("dm", user, FOLLOW_PROMPT, "FOLLOW_CHECK:" + rule["id"])
                        self.repo.set_pending(user, rule["id"])
                        self.repo.finish(event_key, "follow_requested", "confirmed not following")
                        return "follow_requested"
                result = self._send("dm", user, rule["reply"])
                if not result.get("message_id") and not result.get("id"):
                    raise RuntimeError("Meta did not confirm content delivery")
                self.repo.clear_pending(user)
                self.repo.finish(event_key, "delivered", "Meta send confirmed")
                return "delivered"
            except Exception as exc:
                self.repo.finish(event_key, "error", type(exc).__name__)
                return "error"


def parse_webhook(data: dict) -> list[dict]:
    if data.get("object") != "instagram":
        return []
    out = []
    for entry in data.get("entry", []):
        business = str(entry.get("id", ""))
        for change in entry.get("changes", []):
            if change.get("field") != "comments": continue
            val = change.get("value") or {}
            out.append({"type": "comment", "business_id": business,
                        "comment_id": str(val.get("id", "")), "media_id": str((val.get("media") or {}).get("id", "")),
                        "text": val.get("text", ""), "user_id": str((val.get("from") or {}).get("id", "")),
                        "created_at": val.get("timestamp", val.get("created_time")),
                        "is_self_reply": bool(val.get("is_self_reply"))})
        for msg in entry.get("messaging", []):
            sender = str((msg.get("sender") or {}).get("id", ""))
            content = msg.get("message") or {}
            postback = msg.get("postback") or {}
            payload = (content.get("quick_reply") or {}).get("payload") or postback.get("payload", "")
            msg_id = content.get("mid") or postback.get("mid")
            if not msg_id:
                # Some postbacks have no mid; use timestamp + sender + payload for idempotency.
                import hashlib
                stable = f"{sender}:{msg.get('timestamp')}:{payload}"
                if payload and msg.get("timestamp"):
                    msg_id = "postback-" + hashlib.sha256(stable.encode("utf8")).hexdigest()
                else: continue
            out.append({"type": "dm", "business_id": business, "user_id": sender,
                        "message_id": str(msg_id), "text": content.get("text", ""), "payload": payload,
                        "timestamp_ms": msg.get("timestamp"), "is_echo": content.get("is_echo", False)})
    return out
