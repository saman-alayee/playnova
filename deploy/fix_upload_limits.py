#!/usr/bin/env python3
"""Apply PHP upload limits on production and restart php-fpm."""
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent
INI = (ROOT / "deploy" / "php-playnova-uploads.ini").read_text(encoding="utf-8")

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=60, banner_timeout=60)

paths = [
    "/etc/php.d/99-playnova-uploads.ini",
    "/etc/php/8.2/fpm/conf.d/99-playnova-uploads.ini",
    "/etc/php/8.1/fpm/conf.d/99-playnova-uploads.ini",
]

sftp = client.open_sftp()
for remote in paths:
    try:
        with sftp.file(remote, "w") as f:
            f.write(INI)
        print("wrote", remote)
    except OSError as e:
        print("skip", remote, e)
sftp.close()

restart_cmds = [
    "systemctl restart php-fpm 2>/dev/null || true",
    "systemctl restart php82-php-fpm 2>/dev/null || true",
    "systemctl restart php81-php-fpm 2>/dev/null || true",
]
for cmd in restart_cmds:
    client.exec_command(cmd)

_, stdout, _ = client.exec_command(
    'php -r "echo ini_get(\'upload_max_filesize\').\"\\n\".ini_get(\'post_max_size\');"'
)
print("php limits now:", stdout.read().decode().strip())
client.close()
