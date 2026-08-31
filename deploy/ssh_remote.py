#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ACTION = sys.argv[2] if len(sys.argv) > 2 else "status"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)


def run(cmd: str) -> str:
    print("===", cmd[:100], "===")
    _, stdout, stderr = client.exec_command(cmd, get_pty=True)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    result = out or err
    sys.stdout.buffer.write(result.encode("utf-8", errors="replace"))
    sys.stdout.buffer.write(b"\n")
    return result


if ACTION == "upload-health":
    local = Path(__file__).resolve().parent.parent / "PlayNova/app/Http/Controllers/Api/V1/HealthController.php"
    remote = "/var/www/playnova/PlayNova/app/Http/Controllers/Api/V1/HealthController.php"
    sftp = client.open_sftp()
    sftp.put(str(local), remote)
    sftp.close()
    run("cd /var/www/playnova/PlayNova && php artisan config:cache")
    run("curl -s http://127.0.0.1/api/v1/health")
elif ACTION == "upload-sms-fix":
    local = Path(__file__).resolve().parent.parent / "PlayNova/app/Jobs/SendOtpSmsJob.php"
    remote = "/var/www/playnova/PlayNova/app/Jobs/SendOtpSmsJob.php"
    sftp = client.open_sftp()
    sftp.put(str(local), remote)
    sftp.close()
    run("cd /var/www/playnova/PlayNova && php artisan optimize:clear")
elif ACTION == "upload-csrf-fix":
    base = Path(__file__).resolve().parent.parent / "PlayNova"
    files = [
        (base / "app/Http/Kernel.php", "/var/www/playnova/PlayNova/app/Http/Kernel.php"),
        (base / "app/Http/Middleware/VerifyCsrfToken.php", "/var/www/playnova/PlayNova/app/Http/Middleware/VerifyCsrfToken.php"),
    ]
    sftp = client.open_sftp()
    for local, remote in files:
        sftp.put(str(local), remote)
    sftp.close()
    run("cd /var/www/playnova/PlayNova && php artisan config:cache")
elif ACTION == "deploy-frontend":
    run("cd /var/www/playnova && git pull --ff-only origin main")
    run("cd /var/www/playnova/frontend && npm ci && npm run build")
    run("pm2 restart playnova-frontend")
    run("curl -sI http://127.0.0.1/ | head -3")
elif ACTION == "status":
    run("curl -s http://127.0.0.1/api/v1/health")
    run("pm2 list")
    run("systemctl is-active redis nginx")
    run("nginx -T 2>/dev/null | grep -E 'server_name|listen |root |proxy_pass' | head -50")
    run("curl -sI http://127.0.0.1/ | head -5")
    run("curl -sI https://playnova.ir/ 2>/dev/null | head -10 || echo 'no https locally'")
else:
    run(ACTION)

client.close()
