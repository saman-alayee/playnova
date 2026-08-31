#!/usr/bin/env python3
"""Full deploy of all local project changes to production."""
import paramiko
import subprocess
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent
REMOTE_ROOT = "/var/www/playnova"

SKIP_PREFIXES = ("deploy/", ".git/", "node_modules/", "vendor/", ".nuxt/", ".output/")


def collect_files():
    result = subprocess.run(
        ["git", "status", "--porcelain"],
        cwd=ROOT,
        capture_output=True,
        text=True,
        check=True,
    )
    files = []
    for line in result.stdout.splitlines():
        if not line.strip():
            continue
        path = line[3:].strip().strip('"')
        if path.startswith(SKIP_PREFIXES) or path.startswith("deploy\\"):
            continue
        if not (path.startswith("PlayNova/") or path.startswith("frontend/")):
            continue
        local = ROOT / path.replace("/", "\\") if "\\" in str(ROOT) else ROOT / path
        if not local.exists() or local.is_dir():
            continue
        remote = f"{REMOTE_ROOT}/{path.replace(chr(92), '/')}"
        files.append((path.replace("\\", "/"), remote))
    return sorted(set(files))


FILES = collect_files()

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

print(f"=== UPLOAD ({len(FILES)} files) ===")
sftp = client.open_sftp()
for local_rel, remote in FILES:
    local_path = ROOT / local_rel
    remote_dir = "/".join(remote.split("/")[:-1])
    client.exec_command(f"mkdir -p {remote_dir}")
    sftp.put(str(local_path), remote)
    print("uploaded", local_rel)
sftp.close()

cmds = [
    f"cd {REMOTE_ROOT}/PlayNova && php artisan migrate --force",
    f"cd {REMOTE_ROOT}/PlayNova && php artisan route:cache",
    f"cd {REMOTE_ROOT}/PlayNova && php artisan config:cache",
    f"cd {REMOTE_ROOT}/frontend && NUXT_TELEMETRY_DISABLED=1 CI=true npm run build",
    "pm2 restart playnova-frontend",
    "pm2 restart playnova-worker",
    "pm2 list",
]
for cmd in cmds:
    print("===", cmd)
    _, stdout, _ = client.exec_command(cmd, get_pty=True)
    out = stdout.read()
    sys.stdout.buffer.write(out[-1500:] if isinstance(out, str) else out[-1500:])
client.close()
print("\nDEPLOY DONE")
