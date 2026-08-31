#!/usr/bin/env python3
import paramiko, sys
HOST, USER, PASSWORD = "193.105.234.54", "root", sys.argv[1]
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)
cmds = [
    "cat /etc/nginx/conf.d/playnova.conf",
    "curl -s -w '\\nHTTP:%{http_code}\\n' http://127.0.0.1/api/v1/health",
    "curl -s -w '\\nHTTP:%{http_code}\\n' http://127.0.0.1/api/v1/tournaments",
    "curl -s -w '\\nHTTP:%{http_code}\\n' -H 'Host: playnova.ir' http://127.0.0.1/api/v1/health",
    "curl -s -w '\\nHTTP:%{http_code}\\n' -H 'Host: playnova.ir' http://127.0.0.1/admin",
    "ss -tlnp | grep -E ':80|:443|:3000|:9000'",
    "systemctl is-active php-fpm nginx",
    "pm2 list",
]
for c in cmds:
    _, stdout, _ = client.exec_command(c)
    out = stdout.read()
    sys.stdout.buffer.write(b'\n>>> ' + c.encode() + b'\n')
    sys.stdout.buffer.write(out)
client.close()
