#!/usr/bin/env python3
"""Smoke-test tournament result AI admin endpoints (config + analyze shape)."""

from __future__ import annotations

import json
import mimetypes
import os
import sys
import urllib.error
import urllib.request
from io import BytesIO

BASE = os.environ.get("PLAYNOVA_BASE", "http://127.0.0.1:8000").rstrip("/")
TOKEN = os.environ.get("PLAYNOVA_ADMIN_TOKEN", "").strip()
TOURNAMENT_ID = os.environ.get("PLAYNOVA_TOURNAMENT_ID", "").strip()


def request_json(method: str, path: str, body: bytes | None = None, headers: dict | None = None):
    req_headers = {"Accept": "application/json"}
    if headers:
        req_headers.update(headers)
    req = urllib.request.Request(BASE + path, data=body, headers=req_headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            return resp.status, json.loads(raw)
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="replace")
        try:
            return exc.code, json.loads(raw)
        except json.JSONDecodeError:
            return exc.code, {"raw": raw[:500]}


def multipart_analyze(tournament_id: str, token: str):
    boundary = "----PlayNovaBoundary7MA4YWxk"
    image_bytes = (
        b"\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
        b"\xff\xdb\x00C\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\t\t\x08\n\x0c"
        b"\x14\r\x0c\x0b\x0b\x0c\x19\x12\x13\x0f\x14\x1d\x1a\x1f\x1e\x1d\x1a\x1c"
        b"\x1c $.\x27 ,#\x1c\x1c(7),01444\x1f\x27=9=82<342\xff\xc0\x00\x0b\x08"
        b"\x00\x01\x00\x01\x01\x01\x11\x00\xff\xc4\x00\x1f\x00\x00\x01\x05\x01\x01"
        b"\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07"
        b"\x08\t\n\x0b\xff\xda\x00\x08\x01\x01\x00\x00?\x00*\xbf\xff\xd9"
    )

    parts: list[bytes] = []
    parts.append(f"--{boundary}\r\n".encode())
    parts.append(b'Content-Disposition: form-data; name="screenshot"; filename="scoreboard.jpg"\r\n')
    parts.append(b"Content-Type: image/jpeg\r\n\r\n")
    parts.append(image_bytes)
    parts.append(b"\r\n")
    parts.append(f"--{boundary}--\r\n".encode())

    body = b"".join(parts)
    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": f"multipart/form-data; boundary={boundary}",
    }
    return request_json("POST", f"/api/v1/admin/tournaments/{tournament_id}/result-ai/analyze", body, headers)


def main() -> int:
    if not TOKEN:
        print("Set PLAYNOVA_ADMIN_TOKEN (use deploy/get_admin_token.php on server).", file=sys.stderr)
        return 1

    if not TOURNAMENT_ID:
        print("Set PLAYNOVA_TOURNAMENT_ID.", file=sys.stderr)
        return 1

    print(f"Base: {BASE}")
    print(f"Tournament: {TOURNAMENT_ID}")

    code, config = request_json(
        "GET",
        f"/api/v1/admin/tournaments/{TOURNAMENT_ID}/result-ai/config",
        headers={"Authorization": f"Bearer {TOKEN}"},
    )
    print(f"\n[config] HTTP {code}")
    print(json.dumps(config, ensure_ascii=False, indent=2)[:1200])

    if code != 200 or not config.get("success"):
        return 2

    code, analyze = multipart_analyze(TOURNAMENT_ID, TOKEN)
    print(f"\n[analyze] HTTP {code}")
    print(json.dumps(analyze, ensure_ascii=False, indent=2)[:2000])

    if code != 200 or not analyze.get("success"):
        return 3

    data = analyze.get("data") or {}
    coverage = data.get("coverage") or {}
    checks = {
        "has_matched": isinstance(data.get("matched"), list),
        "has_coverage": isinstance(coverage, dict),
        "has_frames_analyzed": "frames_analyzed" in data,
        "coverage_keys": sorted(coverage.keys()) if isinstance(coverage, dict) else [],
    }
    print("\n[checks]", json.dumps(checks, ensure_ascii=False, indent=2))

    return 0 if all(checks.values()) else 4


if __name__ == "__main__":
    raise SystemExit(main())
