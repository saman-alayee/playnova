#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent

FILES = [
    ("frontend/composables/useAuthMenuReady.ts", "/var/www/playnova/frontend/composables/useAuthMenuReady.ts"),
    ("frontend/stores/auth.ts", "/var/www/playnova/frontend/stores/auth.ts"),
    ("frontend/components/AppSidebar.vue", "/var/www/playnova/frontend/components/AppSidebar.vue"),
    ("frontend/components/AppHeader.vue", "/var/www/playnova/frontend/components/AppHeader.vue"),
    ("frontend/components/AppFooter.vue", "/var/www/playnova/frontend/components/AppFooter.vue"),
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
    "cd /var/www/playnova/frontend && npm run build",
    "pm2 restart playnova-frontend",
    "curl -sI http://127.0.0.1/ | head -3",
]
for cmd in cmds:
    print("===", cmd, "===")
    _, stdout, stderr = client.exec_command(cmd, get_pty=True)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    sys.stdout.buffer.write((out or err).encode("utf-8", errors="replace"))
    sys.stdout.buffer.write(b"\n")

client.close()
