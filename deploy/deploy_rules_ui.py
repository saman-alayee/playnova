#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent

FILES = [
    ("frontend/pages/admin/rules/manage.vue", "/var/www/playnova/frontend/pages/admin/rules/manage.vue"),
    ("frontend/pages/admin/rules/[id]/edit.vue", "/var/www/playnova/frontend/pages/admin/rules/[id]/edit.vue"),
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
    "cd /var/www/playnova/frontend && NUXT_TELEMETRY_DISABLED=1 CI=true npm run build",
    "pm2 restart playnova-frontend",
]
for cmd in cmds:
    print("===", cmd, "===")
    _, stdout, stderr = client.exec_command(cmd, get_pty=True)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    sys.stdout.buffer.write((out or err)[-1200:].encode("utf-8", errors="replace"))
    sys.stdout.buffer.write(b"\n")

client.close()
print("done")
