#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent

FILES = [
    ("PlayNova/app/Http/Controllers/Api/V1/Admin/ResourceController.php", "/var/www/playnova/PlayNova/app/Http/Controllers/Api/V1/Admin/ResourceController.php"),
    ("PlayNova/app/Http/Resources/V1/UserResource.php", "/var/www/playnova/PlayNova/app/Http/Resources/V1/UserResource.php"),
    ("frontend/pages/admin/users/index.vue", "/var/www/playnova/frontend/pages/admin/users/index.vue"),
    ("frontend/types/api.ts", "/var/www/playnova/frontend/types/api.ts"),
    ("frontend/components/AdminPagination.vue", "/var/www/playnova/frontend/components/AdminPagination.vue"),
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

sftp = client.open_sftp()
for local_rel, remote in FILES:
    sftp.put(str(ROOT / local_rel), remote)
    print("uploaded", local_rel)
sftp.close()

cmds = [
    "cd /var/www/playnova/PlayNova && php artisan config:cache",
    "cd /var/www/playnova/frontend && NUXT_TELEMETRY_DISABLED=1 CI=true npm run build",
    "pm2 restart playnova-frontend",
]
for cmd in cmds:
    print("===", cmd)
    _, stdout, _ = client.exec_command(cmd, get_pty=True)
    out = stdout.read().decode("utf-8", errors="replace")
    sys.stdout.buffer.write(out[-800:].encode("utf-8", errors="replace"))
client.close()
