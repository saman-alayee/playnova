#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent

FILES = [
    ("frontend/layouts/blank.vue", "/var/www/playnova/frontend/layouts/blank.vue"),
    ("frontend/components/TournamentSeatGrid.vue", "/var/www/playnova/frontend/components/TournamentSeatGrid.vue"),
    ("frontend/pages/tournaments/[id]/select-seat.vue", "/var/www/playnova/frontend/pages/tournaments/[id]/select-seat.vue"),
    ("frontend/pages/admin/tournament-seats/[id].vue", "/var/www/playnova/frontend/pages/admin/tournament-seats/[id].vue"),
    ("frontend/composables/useApi.ts", "/var/www/playnova/frontend/composables/useApi.ts"),
    (
        "PlayNova/app/Http/Controllers/Api/V1/Admin/TournamentSeatAdminController.php",
        "/var/www/playnova/PlayNova/app/Http/Controllers/Api/V1/Admin/TournamentSeatAdminController.php",
    ),
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

sftp = client.open_sftp()
for local_rel, remote in FILES:
    sftp.put(str(ROOT / local_rel), remote)
    print("uploaded", local_rel)
sftp.close()

_, stdout, _ = client.exec_command(
    "cd /var/www/playnova/frontend && NUXT_TELEMETRY_DISABLED=1 CI=true npm run build && pm2 restart playnova-frontend",
    get_pty=True,
)
out = stdout.read()
sys.stdout.buffer.write(out[-1200:] if isinstance(out, str) else out[-1200:])
client.close()
print("done")
