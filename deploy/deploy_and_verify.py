#!/usr/bin/env python3
"""Deploy all pending local changes and run post-deploy checks."""
import json
import paramiko
import sys
import urllib.error
import urllib.request
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent
REMOTE_ROOT = "/var/www/playnova"

# Collect all tracked-ish project files (backend + frontend), skip deploy scripts
SKIP_PARTS = {".git", "node_modules", "vendor", ".nuxt", ".output", "deploy"}

def collect_files():
    files = []
    for base, dirs, names in sorted((ROOT / "PlayNova").walk()):
        dirs[:] = [d for d in dirs if d not in SKIP_PARTS]
        for name in names:
            if name.endswith((".php", ".json")) and "vendor" not in base.parts:
                rel = (base / name).relative_to(ROOT).as_posix()
                files.append((rel, f"{REMOTE_ROOT}/{rel}"))
    for base, dirs, names in sorted((ROOT / "frontend").walk()):
        dirs[:] = [d for d in dirs if d not in SKIP_PARTS]
        for name in names:
            if name.endswith((".vue", ".ts", ".css", ".json")):
                rel = (base / name).relative_to(ROOT).as_posix()
                files.append((rel, f"{REMOTE_ROOT}/{rel}"))
    return files

FILES = [
    # Always include critical recent files explicitly
    ("PlayNova/app/Services/TournamentPrizeTableParser.php", f"{REMOTE_ROOT}/PlayNova/app/Services/TournamentPrizeTableParser.php"),
    ("PlayNova/app/Services/TournamentResultVisionService.php", f"{REMOTE_ROOT}/PlayNova/app/Services/TournamentResultVisionService.php"),
    ("PlayNova/app/Modules/Tournament/Services/TournamentPrizeService.php", f"{REMOTE_ROOT}/PlayNova/app/Modules/Tournament/Services/TournamentPrizeService.php"),
    ("PlayNova/app/Models/Setting.php", f"{REMOTE_ROOT}/PlayNova/app/Models/Setting.php"),
    ("PlayNova/app/Services/ZibalGatewayService.php", f"{REMOTE_ROOT}/PlayNova/app/Services/ZibalGatewayService.php"),
    ("PlayNova/app/Http/Controllers/Api/V1/Admin/SettingsAdminController.php", f"{REMOTE_ROOT}/PlayNova/app/Http/Controllers/Api/V1/Admin/SettingsAdminController.php"),
    ("PlayNova/config/services.php", f"{REMOTE_ROOT}/PlayNova/config/services.php"),
    ("PlayNova/routes/api/v1.php", f"{REMOTE_ROOT}/PlayNova/routes/api/v1.php"),
    ("frontend/layouts/admin.vue", f"{REMOTE_ROOT}/frontend/layouts/admin.vue"),
    ("frontend/components/AdminHelpBanner.vue", f"{REMOTE_ROOT}/frontend/components/AdminHelpBanner.vue"),
    ("frontend/composables/useAdminHelp.ts", f"{REMOTE_ROOT}/frontend/composables/useAdminHelp.ts"),
    ("frontend/composables/useApi.ts", f"{REMOTE_ROOT}/frontend/composables/useApi.ts"),
    ("frontend/nuxt.config.ts", f"{REMOTE_ROOT}/frontend/nuxt.config.ts"),
    ("frontend/pages/admin/payment-gateway.vue", f"{REMOTE_ROOT}/frontend/pages/admin/payment-gateway.vue"),
    ("frontend/pages/admin/sms-settings.vue", f"{REMOTE_ROOT}/frontend/pages/admin/sms-settings.vue"),
    ("frontend/pages/admin/admins/index.vue", f"{REMOTE_ROOT}/frontend/pages/admin/admins/index.vue"),
    ("frontend/pages/admin/seat-admins/index.vue", f"{REMOTE_ROOT}/frontend/pages/admin/seat-admins/index.vue"),
    ("frontend/pages/admin/logo.vue", f"{REMOTE_ROOT}/frontend/pages/admin/logo.vue"),
    ("frontend/pages/admin/rules/manage.vue", f"{REMOTE_ROOT}/frontend/pages/admin/rules/manage.vue"),
    ("frontend/pages/admin/rules/[id]/edit.vue", f"{REMOTE_ROOT}/frontend/pages/admin/rules/[id]/edit.vue"),
    ("frontend/pages/admin/tournament-seats/index.vue", f"{REMOTE_ROOT}/frontend/pages/admin/tournament-seats/index.vue"),
    ("frontend/pages/admin/tournaments/[id]/result.vue", f"{REMOTE_ROOT}/frontend/pages/admin/tournaments/[id]/result.vue"),
    ("frontend/pages/wallet.vue", f"{REMOTE_ROOT}/frontend/pages/wallet.vue"),
    ("frontend/pages/wallet/pay/[trackId].vue", f"{REMOTE_ROOT}/frontend/pages/wallet/pay/[trackId].vue"),
    ("frontend/pages/tournaments/[id]/select-seat.vue", f"{REMOTE_ROOT}/frontend/pages/tournaments/[id]/select-seat.vue"),
    ("frontend/types/api.ts", f"{REMOTE_ROOT}/frontend/types/api.ts"),
]

# Deduplicate while preserving order
seen = set()
UNIQUE_FILES = []
for local_rel, remote in FILES:
    if local_rel not in seen:
        seen.add(local_rel)
        UNIQUE_FILES.append((local_rel, remote))

print("=== UPLOAD ===")
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

sftp = client.open_sftp()
uploaded = 0
for local_rel, remote in UNIQUE_FILES:
    local_path = ROOT / local_rel
    if not local_path.exists():
        print("SKIP missing", local_rel)
        continue
    remote_dir = "/".join(remote.split("/")[:-1])
    client.exec_command(f"mkdir -p {remote_dir}")
    sftp.put(str(local_path), remote)
    uploaded += 1
    print("uploaded", local_rel)
sftp.close()
print(f"uploaded {uploaded} files")

print("\n=== SERVER COMMANDS ===")
remote_cmds = " && ".join([
    f"cd {REMOTE_ROOT}/PlayNova && php artisan migrate --force",
    f"cd {REMOTE_ROOT}/PlayNova && php artisan route:cache",
    f"cd {REMOTE_ROOT}/PlayNova && php artisan config:cache",
    f"cd {REMOTE_ROOT}/frontend && NUXT_TELEMETRY_DISABLED=1 CI=true npm run build",
    "pm2 restart playnova-frontend",
    "pm2 restart playnova-worker",
    "pm2 list",
])
_, stdout, stderr = client.exec_command(remote_cmds, get_pty=True)
out = stdout.read()
err = stderr.read()
if isinstance(out, str):
    sys.stdout.write(out[-2500:])
else:
    sys.stdout.buffer.write(out[-2500:])
if err:
    sys.stderr.write(err.decode() if isinstance(err, bytes) else err)
client.close()

print("\n=== HTTP CHECKS ===")
BASE = "https://playnova.ir"
checks = [
    ("GET", f"{BASE}/", None),
    ("GET", f"{BASE}/api/v1/tournaments", None),
    ("GET", f"{BASE}/api/v1/news", None),
    ("GET", f"{BASE}/admin", None),
    ("GET", f"{BASE}/wallet", None),
    ("GET", f"{BASE}/tournaments", None),
]

failures = []
for method, url, body in checks:
    try:
        req = urllib.request.Request(url, method=method, headers={"User-Agent": "PlayNovaDeployCheck/1.0"})
        with urllib.request.urlopen(req, timeout=30) as resp:
            code = resp.getcode()
            snippet = resp.read(500).decode("utf-8", errors="replace")
            ok = code < 400
            if url.endswith("/api/v1/tournaments") and '"success"' not in snippet and '"data"' not in snippet:
                ok = False
            status = "OK" if ok else "WARN"
            print(f"{status} {code} {url}")
            if not ok:
                failures.append((url, code, snippet[:200]))
    except urllib.error.HTTPError as e:
        # 302/401 on admin is acceptable
        if e.code in (301, 302, 401, 403) and "/admin" in url:
            print(f"OK {e.code} {url} (auth redirect)")
        else:
            print(f"FAIL {e.code} {url}")
            failures.append((url, e.code, str(e)))
    except Exception as e:
        print(f"FAIL {url}: {e}")
        failures.append((url, 0, str(e)))

print("\n=== LARAVEL LOG (last errors) ===")
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)
_, stdout, _ = client.exec_command(
    f"tail -n 30 {REMOTE_ROOT}/PlayNova/storage/logs/laravel.log 2>/dev/null | grep -E 'ERROR|CRITICAL|Exception' | tail -n 8 || echo 'no recent errors'"
)
log_out = stdout.read().decode()
print(log_out.strip() or "(no error lines)")
client.close()

if failures:
    print("\n=== FAILURES ===")
    for item in failures:
        print(item)
    sys.exit(1)

print("\nDeploy and checks completed successfully.")
