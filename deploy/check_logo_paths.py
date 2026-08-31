#!/usr/bin/env python3
import paramiko
import sys

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

cmds = [
    "ls -la /var/www/playnova/",
    "ls -la /var/www/playnova/PlayNova/public/ | head -15",
    "ls -la /var/www/playnova/frontend/public/ | head -15",
    "grep -r storage /etc/nginx/sites-enabled/ 2>/dev/null | head -20",
    "cat /etc/nginx/sites-enabled/playnova* 2>/dev/null | head -80",
]

for cmd in cmds:
    print("===", cmd, "===")
    _, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    sys.stdout.buffer.write((out or err).encode("utf-8", errors="replace"))
    sys.stdout.buffer.write(b"\n")

client.close()
