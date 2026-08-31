#!/usr/bin/env python3
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
sftp.put(
    str(ROOT / "PlayNova/app/Services/ZibalGatewayService.php"),
    "/var/www/playnova/PlayNova/app/Services/ZibalGatewayService.php",
)
sftp.close()
print("uploaded ZibalGatewayService.php")

cmds = [
    "sed -i 's|^FRONTEND_URL=.*|FRONTEND_URL=https://playnova.ir|' /var/www/playnova/PlayNova/.env",
    "grep FRONTEND_URL /var/www/playnova/PlayNova/.env",
    "cd /var/www/playnova/PlayNova && php artisan config:clear",
    'cd /var/www/playnova/PlayNova && php artisan tinker --execute="echo json_encode([\'origin\' => app(\\App\\Services\\ZibalGatewayService::class)->siteOrigin(), \'callback\' => app(\\App\\Services\\ZibalGatewayService::class)->callbackUrl()]);"',
]

for cmd in cmds:
    print("===", cmd, "===")
    _, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    sys.stdout.buffer.write((out or err).encode("utf-8", errors="replace"))
    sys.stdout.buffer.write(b"\n")

client.close()
