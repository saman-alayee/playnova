#!/usr/bin/env python3
import paramiko
import sys

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1] if len(sys.argv) > 1 else ""

cmds = [
    "curl -s http://127.0.0.1/api/v1/health",
    "curl -s http://127.0.0.1/api/v1/settings | head -c 300",
    "pm2 list",
    "systemctl is-active redis nginx",
    "grep -E '^(APP_URL|REDIS_|QUEUE_|CACHE_|SESSION_)' /var/www/playnova/PlayNova/.env | head -20",
    "ls -la /etc/nginx/conf.d/ 2>/dev/null; echo '---'; cat /etc/nginx/conf.d/playnova.conf 2>/dev/null || cat /etc/nginx/sites-enabled/playnova 2>/dev/null || nginx -T 2>/dev/null | grep -A5 server_name",
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

for cmd in cmds:
    print("===", cmd, "===")
    _, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode()
    err = stderr.read().decode()
    sys.stdout.buffer.write((out or err).encode("utf-8", errors="replace"))
    sys.stdout.buffer.write(b"\n")

client.close()
