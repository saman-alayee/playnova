#!/usr/bin/env python3
"""Create or refresh a solo test tournament on production."""
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

sftp = client.open_sftp()
sftp.put(str(ROOT / "deploy/create_test_tournament.php"), "/var/www/playnova/deploy/create_test_tournament.php")
sftp.close()

_, stdout, stderr = client.exec_command("php /var/www/playnova/deploy/create_test_tournament.php", get_pty=True)
out = stdout.read().decode("utf-8", errors="replace")
err = stderr.read().decode("utf-8", errors="replace")
client.close()

text = out or err
sys.stdout.buffer.write(text.encode("utf-8", errors="replace"))
sys.stdout.buffer.write(b"\n")
if '"id"' in text:
    print("TEST_TOURNAMENT_OK")
else:
    sys.exit(1)
