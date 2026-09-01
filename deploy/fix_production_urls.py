#!/usr/bin/env python3
"""Fix production URLs: same-origin API, nginx server_name, rebuild frontend."""
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent
REMOTE = "/var/www/playnova"

FILES = [
    ("frontend/nuxt.config.ts", f"{REMOTE}/frontend/nuxt.config.ts"),
    ("frontend/composables/useApi.ts", f"{REMOTE}/frontend/composables/useApi.ts"),
    ("frontend/composables/useModals.ts", f"{REMOTE}/frontend/composables/useModals.ts"),
    ("frontend/components/RegisterTournamentModal.vue", f"{REMOTE}/frontend/components/RegisterTournamentModal.vue"),
    ("frontend/pages/tournaments/[id]/select-seat.vue", f"{REMOTE}/frontend/pages/tournaments/[id]/select-seat.vue"),
    ("frontend/assets/css/main.css", f"{REMOTE}/frontend/assets/css/main.css"),
    ("PlayNova/config/cors.php", f"{REMOTE}/PlayNova/config/cors.php"),
    ("deploy/nginx-playnova.conf", "/etc/nginx/conf.d/playnova.conf"),
]

FRONTEND_ENV = """NUXT_PUBLIC_API_BASE=/api/v1
NUXT_PUBLIC_BACKEND_URL=
"""

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=60)

print("=== UPLOAD ===")
sftp = client.open_sftp()
for local_rel, remote in FILES:
    local_path = ROOT / local_rel.replace("/", "\\") if "\\" in str(ROOT) else ROOT / local_rel
    remote_dir = "/".join(remote.split("/")[:-1])
    if remote_dir:
        client.exec_command(f"mkdir -p {remote_dir}")
    sftp.put(str(local_path), remote)
    print("uploaded", local_rel)
with sftp.open(f"{REMOTE}/frontend/.env", "w") as f:
    f.write(FRONTEND_ENV)
sftp.close()
print("updated frontend/.env")

cmds = [
    "nginx -t",
    "systemctl reload nginx",
    f"cd {REMOTE}/PlayNova && php artisan config:cache",
    f"cd {REMOTE}/frontend && NUXT_TELEMETRY_DISABLED=1 CI=true npm run build",
    "pm2 restart playnova-frontend",
    "grep apiBase /var/www/playnova/frontend/.output/server/chunks/nitro/nitro.mjs | head -1",
    "curl -s http://127.0.0.1/api/v1/health | head -c 120",
    f"grep -E '^(APP_URL|FRONTEND_URL)=' {REMOTE}/PlayNova/.env",
]

for cmd in cmds:
    print("===", cmd)
    _, stdout, stderr = client.exec_command(cmd, get_pty=True)
    out = stdout.read()
    sys.stdout.buffer.write(out[-1500:] if len(out) > 1500 else out)
    if not out:
        err = stderr.read()
        if err:
            sys.stdout.buffer.write(err[-500:])

client.close()
print("\nDONE")
