#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent

FILES = [
    ("frontend/components/AppSidebar.vue", "/var/www/playnova/frontend/components/AppSidebar.vue"),
    ("frontend/components/AdminNav.vue", "/var/www/playnova/frontend/components/AdminNav.vue"),
    ("frontend/pages/tickets/index.vue", "/var/www/playnova/frontend/pages/tickets/index.vue"),
    ("frontend/pages/contact.vue", "/var/www/playnova/frontend/pages/contact.vue"),
    ("frontend/pages/profile.vue", "/var/www/playnova/frontend/pages/profile.vue"),
    ("frontend/pages/admin/index.vue", "/var/www/playnova/frontend/pages/admin/index.vue"),
    ("frontend/pages/support/index.vue", "/var/www/playnova/frontend/pages/support/index.vue"),
    ("frontend/pages/support/[id].vue", "/var/www/playnova/frontend/pages/support/[id].vue"),
    ("frontend/pages/admin/tickets/index.vue", "/var/www/playnova/frontend/pages/admin/tickets/index.vue"),
    ("frontend/pages/admin/tickets/[id].vue", "/var/www/playnova/frontend/pages/admin/tickets/[id].vue"),
    ("frontend/composables/useApi.ts", "/var/www/playnova/frontend/composables/useApi.ts"),
    ("frontend/composables/useAdminHelp.ts", "/var/www/playnova/frontend/composables/useAdminHelp.ts"),
    ("frontend/nuxt.config.ts", "/var/www/playnova/frontend/nuxt.config.ts"),
    ("PlayNova/routes/api/v1.php", "/var/www/playnova/PlayNova/routes/api/v1.php"),
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

sftp = client.open_sftp()
for local_rel, remote in FILES:
    sftp.put(str(ROOT / local_rel), remote)
    print("uploaded", local_rel)
sftp.close()

_, stdout, _ = client.exec_command(
    "cd /var/www/playnova/frontend && NUXT_TELEMETRY_DISABLED=1 CI=true npm run build && pm2 restart playnova-frontend",
    get_pty=True,
)
out = stdout.read()
sys.stdout.buffer.write(out[-1200:] if isinstance(out, str) else out[-1200:])
client.close()
print("done")
