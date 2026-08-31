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
    ("PlayNova/app/Http/Controllers/Api/V1/Admin/ContentAdminController.php", "/var/www/playnova/PlayNova/app/Http/Controllers/Api/V1/Admin/ContentAdminController.php"),
    ("PlayNova/app/Http/Controllers/Api/V1/Admin/NotificationAdminController.php", "/var/www/playnova/PlayNova/app/Http/Controllers/Api/V1/Admin/NotificationAdminController.php"),
    ("PlayNova/app/Http/Controllers/Api/V1/Admin/UserAdminController.php", "/var/www/playnova/PlayNova/app/Http/Controllers/Api/V1/Admin/UserAdminController.php"),
    ("frontend/components/AdminFilterBar.vue", "/var/www/playnova/frontend/components/AdminFilterBar.vue"),
    ("frontend/components/AdminFilterField.vue", "/var/www/playnova/frontend/components/AdminFilterField.vue"),
    ("frontend/pages/admin/tournaments/index.vue", "/var/www/playnova/frontend/pages/admin/tournaments/index.vue"),
    ("frontend/pages/admin/kyc/index.vue", "/var/www/playnova/frontend/pages/admin/kyc/index.vue"),
    ("frontend/pages/admin/news/index.vue", "/var/www/playnova/frontend/pages/admin/news/index.vue"),
    ("frontend/pages/admin/discounts/index.vue", "/var/www/playnova/frontend/pages/admin/discounts/index.vue"),
    ("frontend/pages/admin/broadcast-manage/index.vue", "/var/www/playnova/frontend/pages/admin/broadcast-manage/index.vue"),
    ("frontend/pages/admin/withdrawals/index.vue", "/var/www/playnova/frontend/pages/admin/withdrawals/index.vue"),
    ("frontend/pages/admin/users/[id]/activity.vue", "/var/www/playnova/frontend/pages/admin/users/[id]/activity.vue"),
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
