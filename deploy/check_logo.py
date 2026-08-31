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
    "find /var/www/playnova -name logo.blade.php 2>/dev/null",
    "test -L /var/www/playnova/PlayNova/public/storage && echo storage_link_ok || echo storage_link_missing",
    "ls -la /var/www/playnova/PlayNova/public/storage 2>/dev/null | head -3",
    "php /var/www/playnova/PlayNova/artisan tinker --execute=\"echo json_encode(['logo'=>\\App\\Models\\Setting::get('logo'),'url'=>\\App\\Models\\Setting::logoUrl()]);\"",
]

for cmd in cmds:
    print("===", cmd, "===")
    _, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    sys.stdout.buffer.write((out or err).encode("utf-8", errors="replace"))
    sys.stdout.buffer.write(b"\n")

client.close()
