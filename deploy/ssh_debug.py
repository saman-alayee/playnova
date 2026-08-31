#!/usr/bin/env python3
import paramiko, sys
HOST, USER, PASSWORD = "193.105.234.54", "root", sys.argv[1]
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)
cmds = [
    "curl -s http://127.0.0.1:8000/api/v1/tournaments 2>&1 | head -c 500",
    "tail -n 80 /var/www/playnova/PlayNova/storage/logs/laravel.log",
    "ls /etc/nginx/sites-enabled/",
    "grep -n 'playnova\\|location\\|proxy_pass\\|try_files' /etc/nginx/sites-enabled/default 2>/dev/null | head -60",
    "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:3000/admin",
    "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:3000/tournaments",
    "curl -s -o /dev/null -w '%{http_code}' -H 'Host: playnova.ir' http://127.0.0.1/admin",
]
for c in cmds:
    _, stdout, _ = client.exec_command(c)
    out = stdout.read()
    sys.stdout.buffer.write(b'\n>>> ' + c.encode() + b'\n')
    sys.stdout.buffer.write(out[-4000:] if len(out) > 4000 else out)
    sys.stdout.buffer.write(b'\n')
client.close()
