#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ROOT = Path(__file__).resolve().parent.parent

FILES = [
    ("PlayNova/app/Services/TournamentPrizeTableParser.php", "/var/www/playnova/PlayNova/app/Services/TournamentPrizeTableParser.php"),
    ("PlayNova/app/Services/TournamentResultVisionService.php", "/var/www/playnova/PlayNova/app/Services/TournamentResultVisionService.php"),
    ("PlayNova/app/Modules/Tournament/Services/TournamentPrizeService.php", "/var/www/playnova/PlayNova/app/Modules/Tournament/Services/TournamentPrizeService.php"),
    ("PlayNova/app/Models/Setting.php", "/var/www/playnova/PlayNova/app/Models/Setting.php"),
    ("PlayNova/config/services.php", "/var/www/playnova/PlayNova/config/services.php"),
    ("frontend/pages/admin/tournaments/[id]/result.vue", "/var/www/playnova/frontend/pages/admin/tournaments/[id]/result.vue"),
    ("frontend/types/api.ts", "/var/www/playnova/frontend/types/api.ts"),
    ("frontend/composables/useAdminHelp.ts", "/var/www/playnova/frontend/composables/useAdminHelp.ts"),
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
sys.stdout.buffer.write(out[-800:] if isinstance(out, str) else out[-800:])
client.close()
print("done")
