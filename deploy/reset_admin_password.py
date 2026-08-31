#!/usr/bin/env python3
import paramiko
import secrets
import string
import sys

HOST = "193.105.234.54"
USER = "root"
SSH_PASSWORD = sys.argv[1]
MOBILE = sys.argv[2] if len(sys.argv) > 2 else "09051770091"
NEW_PASSWORD = sys.argv[3] if len(sys.argv) > 3 else "".join(
    secrets.choice(string.ascii_letters + string.digits) for _ in range(12)
)

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=SSH_PASSWORD, timeout=30)

remote_script = f"""<?php
require '/var/www/playnova/PlayNova/vendor/autoload.php';
$app = require '/var/www/playnova/PlayNova/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

$user = App\\Models\\User::query()
    ->where('mobile', '{MOBILE}')
    ->orWhere('mobile', 'like', '%9051770091%')
    ->first();

if (! $user) {{
    fwrite(STDERR, "NOT_FOUND\\n");
    exit(1);
}}

$user->password = bcrypt('{NEW_PASSWORD}');
$user->save();

echo json_encode([
    'ok' => true,
    'id' => $user->id,
    'name' => $user->name,
    'mobile' => $user->mobile,
    'is_admin' => (bool) $user->is_admin,
], JSON_UNESCAPED_UNICODE);
"""

sftp = client.open_sftp()
with sftp.file("/tmp/reset_admin_pw.php", "w") as f:
    f.write(remote_script)
sftp.close()

_, stdout, stderr = client.exec_command("php /tmp/reset_admin_pw.php && rm -f /tmp/reset_admin_pw.php")
out = stdout.read().decode("utf-8", errors="replace").strip()
err = stderr.read().decode("utf-8", errors="replace").strip()
client.close()

if err or not out:
    sys.stdout.buffer.write(f"ERROR: {err or 'empty output'}\n".encode("utf-8", errors="replace"))
    sys.exit(1)

sys.stdout.buffer.write((out + "\n").encode("utf-8", errors="replace"))
sys.stdout.buffer.write(f"NEW_PASSWORD: {NEW_PASSWORD}\n".encode("utf-8"))
