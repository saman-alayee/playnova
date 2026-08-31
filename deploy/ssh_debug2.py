#!/usr/bin/env python3
import paramiko, sys
HOST, USER, PASSWORD = "193.105.234.54", "root", sys.argv[1]
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)
cmds = [
    "curl -s -w '\\nHTTP:%{http_code}\\n' http://127.0.0.1:8000/api/v1/tournaments",
    "grep -n 'tournaments' /var/www/playnova/PlayNova/storage/logs/laravel.log | tail -5",
    "grep 'local.ERROR' /var/www/playnova/PlayNova/storage/logs/laravel.log | tail -3",
    "find /etc/nginx -name '*.conf' 2>/dev/null | head -10",
    "grep -R 'playnova' /etc/nginx 2>/dev/null | head -30",
    "curl -s -o /dev/null -w '%{http_code}' https://playnova.ir/admin",
    "curl -s -o /dev/null -w '%{http_code}' http://193.105.234.54/admin",
]
for c in cmds:
    _, stdout, stderr = client.exec_command(c)
    out = stdout.read()
    err = stderr.read()
    sys.stdout.buffer.write(b'\n>>> ' + c.encode() + b'\n')
    sys.stdout.buffer.write(out[-6000:] if len(out) > 6000 else out)
    if err: sys.stdout.buffer.write(err)
client.close()
