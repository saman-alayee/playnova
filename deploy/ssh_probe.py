#!/usr/bin/env python3
import paramiko, sys
HOST, USER, PASSWORD = "193.105.234.54", "root", sys.argv[1]
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)
cmds = [
    "grep -r 'api/v1\\|API_BASE\\|proxy_pass' /etc/nginx/sites-enabled/ 2>/dev/null | head -40",
    "cat /var/www/playnova/frontend/.env 2>/dev/null || cat /var/www/playnova/frontend/.env.production 2>/dev/null || echo 'no frontend env'",
    "pm2 show playnova-frontend 2>/dev/null | head -25",
    "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:3000/admin",
    "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:3000/tournaments",
    "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/api/v1/health",
    "curl -s http://127.0.0.1:8000/api/v1/health | head -c 200",
    "curl -s -o /dev/null -w '%{http_code}' https://playnova.ir/api/v1/health",
    "ls /var/www/playnova/frontend/.output/server 2>/dev/null | head -5",
]
for c in cmds:
    print('>>>', c)
    _, stdout, stderr = client.exec_command(c)
    out = stdout.read().decode('utf-8', errors='replace').strip()
    err = stderr.read().decode('utf-8', errors='replace').strip()
    print(out or err or '(empty)')
    print()
client.close()
