#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

sftp = client.open_sftp()
sftp.put(
    str(ROOT / "frontend/pages/admin/admins/index.vue"),
    "/var/www/playnova/frontend/pages/admin/admins/index.vue",
)
sftp.close()
print("uploaded admins/index.vue")

_, stdout, _ = client.exec_command(
    "cd /var/www/playnova/frontend && NUXT_TELEMETRY_DISABLED=1 CI=true npm run build && pm2 restart playnova-frontend",
    get_pty=True,
)
out = stdout.read()
if isinstance(out, str):
    sys.stdout.buffer.write(out[-1200:].encode("utf-8", errors="replace"))
else:
    sys.stdout.buffer.write(out[-1200:])
client.close()
print("done")
