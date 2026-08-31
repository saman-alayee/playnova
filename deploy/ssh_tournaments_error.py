#!/usr/bin/env python3
import paramiko, sys
HOST, USER, PASSWORD = "193.105.234.54", "root", sys.argv[1]
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)
cmds = [
    "curl -s http://127.0.0.1/api/v1/tournaments > /tmp/tournaments_out.txt; echo EXIT:$?",
    "wc -c /tmp/tournaments_out.txt; head -c 800 /tmp/tournaments_out.txt",
    "tail -n 120 /var/www/playnova/PlayNova/storage/logs/laravel.log | tail -n 60",
]
for c in cmds:
    _, stdout, _ = client.exec_command(c)
    out = stdout.read()
    sys.stdout.buffer.write(b'\n>>> ' + c.encode() + b'\n')
    sys.stdout.buffer.write(out)
client.close()
