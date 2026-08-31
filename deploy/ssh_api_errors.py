#!/usr/bin/env python3
import paramiko, sys
HOST, USER, PASSWORD = "193.105.234.54", "root", sys.argv[1]
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)
cmds = [
    "curl -s -w '\\nHTTP:%{http_code}\\n' http://127.0.0.1/api/v1/home | tail -3",
    "curl -s -w '\\nHTTP:%{http_code}\\n' http://127.0.0.1/api/v1/news | tail -5",
    "curl -s -w '\\nHTTP:%{http_code}\\n' http://127.0.0.1/api/v1/settings/public | tail -5",
    "cd /var/www/playnova/PlayNova && php artisan route:list --path=api/v1/news 2>&1 | head -5",
    "grep -n '2026-08-31 23:' /var/www/playnova/PlayNova/storage/logs/laravel.log | tail -5",
    "tail -n 5 /var/www/playnova/PlayNova/storage/logs/laravel.log",
]
for c in cmds:
    _, stdout, stderr = client.exec_command(c)
    out = stdout.read()
    err = stderr.read()
    sys.stdout.buffer.write(b'\n>>> ' + c.encode() + b'\n')
    sys.stdout.buffer.write(out[-3000:])
    if err: sys.stdout.buffer.write(err)
client.close()
