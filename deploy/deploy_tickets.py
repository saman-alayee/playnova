#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent

FILES = [
    ("PlayNova/database/migrations/2026_08_31_000001_create_ticket_messages_table.php", "/var/www/playnova/PlayNova/database/migrations/2026_08_31_000001_create_ticket_messages_table.php"),
    ("PlayNova/app/Http/Resources/V1/TicketResource.php", "/var/www/playnova/PlayNova/app/Http/Resources/V1/TicketResource.php"),
    ("PlayNova/app/Http/Resources/V1/TicketMessageResource.php", "/var/www/playnova/PlayNova/app/Http/Resources/V1/TicketMessageResource.php"),
    ("PlayNova/app/Http/Controllers/Api/V1/Concerns/HandlesTicketAttachments.php", "/var/www/playnova/PlayNova/app/Http/Controllers/Api/V1/Concerns/HandlesTicketAttachments.php"),
    ("PlayNova/app/Http/Controllers/Api/V1/TicketController.php", "/var/www/playnova/PlayNova/app/Http/Controllers/Api/V1/TicketController.php"),
    ("PlayNova/app/Http/Controllers/Api/V1/Admin/TicketAdminController.php", "/var/www/playnova/PlayNova/app/Http/Controllers/Api/V1/Admin/TicketAdminController.php"),
    ("PlayNova/routes/api/v1.php", "/var/www/playnova/PlayNova/routes/api/v1.php"),
    ("frontend/types/api.ts", "/var/www/playnova/frontend/types/api.ts"),
    ("frontend/composables/useApi.ts", "/var/www/playnova/frontend/composables/useApi.ts"),
    ("frontend/pages/support/index.vue", "/var/www/playnova/frontend/pages/support/index.vue"),
    ("frontend/pages/support/[id].vue", "/var/www/playnova/frontend/pages/support/[id].vue"),
    ("frontend/pages/admin/tickets/index.vue", "/var/www/playnova/frontend/pages/admin/tickets/index.vue"),
    ("frontend/pages/admin/tickets/[id].vue", "/var/www/playnova/frontend/pages/admin/tickets/[id].vue"),
    ("frontend/pages/tickets/index.vue", "/var/www/playnova/frontend/pages/tickets/index.vue"),
    ("frontend/pages/contact.vue", "/var/www/playnova/frontend/pages/contact.vue"),
    ("frontend/components/AppSidebar.vue", "/var/www/playnova/frontend/components/AppSidebar.vue"),
    ("frontend/middleware/kyc-gate.global.ts", "/var/www/playnova/frontend/middleware/kyc-gate.global.ts"),
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

sftp = client.open_sftp()
for local_rel, remote in FILES:
    remote_dir = "/".join(remote.split("/")[:-1])
    try:
        sftp.stat(remote_dir)
    except OSError:
        client.exec_command(f"mkdir -p {remote_dir}")
    sftp.put(str(ROOT / local_rel), remote)
    print("uploaded", local_rel)
sftp.close()

cmds = [
    "cd /var/www/playnova/PlayNova && php artisan migrate --force",
    "cd /var/www/playnova/PlayNova && php artisan route:cache",
    "cd /var/www/playnova/PlayNova && php artisan config:cache",
    "cd /var/www/playnova/frontend && NUXT_TELEMETRY_DISABLED=1 CI=true npm run build",
    "pm2 restart playnova-frontend",
]
for cmd in cmds:
    print("===", cmd)
    _, stdout, _ = client.exec_command(cmd, get_pty=True)
    out = stdout.read().decode("utf-8", errors="replace")
    sys.stdout.buffer.write(out[-1000:].encode("utf-8", errors="replace"))
client.close()
