#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent

FILES = [
    ("frontend/layouts/admin.vue", "/var/www/playnova/frontend/layouts/admin.vue"),
    ("frontend/components/AdminHelpBanner.vue", "/var/www/playnova/frontend/components/AdminHelpBanner.vue"),
    ("frontend/composables/useAdminHelp.ts", "/var/www/playnova/frontend/composables/useAdminHelp.ts"),
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
sys.stdout.buffer.write(out[-800:] if isinstance(out, str) else out[-800:])
client.close()
print("done")
