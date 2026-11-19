"""Thin official Instagram Graph API transport, with no credential persistence in app code."""
from __future__ import annotations
import json
import urllib.parse
import urllib.request


class MetaAPI:
    def __init__(self, token: str, ig_user_id: str, version="v25.0", requester=None):
        self.token, self.ig_user_id, self.version = token, ig_user_id, version
        self.requester = requester or urllib.request.urlopen
        self.base = "https://graph.instagram.com/" + version + "/"

    def _request(self, path: str, body: dict | None = None):
        if not self.token: raise RuntimeError("No Instagram access token configured")
        url = self.base + path
        if body is None:
            req = urllib.request.Request(url, headers={"Authorization": "Bearer " + self.token})
        else:
            req = urllib.request.Request(url, data=json.dumps(body, ensure_ascii=False).encode("utf8"),
                                         headers={"Content-Type": "application/json", "Authorization": "Bearer " + self.token}, method="POST")
        try:
            with self.requester(req, timeout=20) as resp:
                result = json.loads(resp.read().decode("utf8"))
                if not isinstance(result, dict): raise RuntimeError("Unexpected Meta response")
                if "error" in result: raise RuntimeError("Meta returned an API error")
                return result
        except Exception as exc:
            # Do not log URL query params, headers or credentials.
            raise RuntimeError("Instagram Graph API operation failed: " + type(exc).__name__) from None

    def follow_status(self, user_id: str) -> bool | None:
        if not user_id.isdecimal(): raise ValueError("Invalid Instagram-scoped user ID")
        data = self._request(user_id + "?" + urllib.parse.urlencode({"fields": "is_user_follow_business"}))
        state = data.get("is_user_follow_business")
        return state if type(state) is bool else None

    def dm(self, recipient_id: str, text: str, quick_payload: str | None = None):
        message = {"text": text}
        if quick_payload:
            message["quick_replies"] = [{"content_type": "text", "title": "فالو کردم ✅", "payload": quick_payload}]
        return self._request(self.ig_user_id + "/messages", {"recipient": {"id": recipient_id}, "message": message})

    def private_reply(self, comment_id: str, text: str, quick_payload: str | None = None):
        # A single permitted private reply. Include the follow-check quick reply in
        # this first message; do NOT silently retry without the button on API errors.
        message = {"text": text}
        if quick_payload:
            message["quick_replies"] = [{"content_type": "text", "title": "فالو کردم ✅", "payload": quick_payload}]
        return self._request(self.ig_user_id + "/messages", {"recipient": {"comment_id": comment_id}, "message": message})

    def public_reply(self, comment_id: str, text: str):
        return self._request(comment_id + "/replies", {"message": text})
