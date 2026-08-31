#!/usr/bin/env python3
"""Post-deploy health check against the live app server."""
import json
import urllib.error
import urllib.request

BASE = "http://193.105.234.54"

CHECKS = [
    ("GET", "/", 200),
    ("GET", "/login", 200),
    ("GET", "/admin", 200),
    ("GET", "/wallet", 200),
    ("GET", "/api/v1/health", 200),
    ("GET", "/api/v1/home", 200),
    ("GET", "/api/v1/settings", 200),
    ("GET", "/api/v1/rules", 200),
    ("GET", "/api/v1/leaderboard", 200),
    ("GET", "/api/v1/pages/faq", 200),
]

failures = []
print("Health check:", BASE)
for method, path, expected in CHECKS:
    url = BASE + path
    try:
        req = urllib.request.Request(url, method=method, headers={"User-Agent": "PlayNovaHealth/1.0"})
        with urllib.request.urlopen(req, timeout=25) as resp:
            code = resp.getcode()
            body = resp.read(300).decode("utf-8", errors="replace")
            ok = code == expected
            if path.startswith("/api/v1/") and '"success"' not in body and code == 200:
                ok = False
            status = "OK" if ok else "FAIL"
            print(f"  {status} {code} {path}")
            if not ok:
                failures.append((path, code, body[:120]))
    except urllib.error.HTTPError as e:
        print(f"  FAIL {e.code} {path}")
        failures.append((path, e.code, str(e)))
    except Exception as e:
        print(f"  FAIL {path}: {e}")
        failures.append((path, 0, str(e)))

print()
print("Domain check: https://playnova.ir")
for path in ["/", "/admin", "/login"]:
    url = "https://playnova.ir" + path
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "PlayNovaHealth/1.0"})
        with urllib.request.urlopen(req, timeout=25) as resp:
            print(f"  {resp.getcode()} {path}")
    except urllib.error.HTTPError as e:
        print(f"  {e.code} {path}")

if failures:
    print("\nFailures on app server:")
    for f in failures:
        print(" ", f)
    raise SystemExit(1)

print("\nAll app-server checks passed.")
