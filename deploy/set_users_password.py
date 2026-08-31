#!/usr/bin/env python3
import paramiko
import secrets
import string
import sys

HOST = "193.105.234.54"
USER = "root"
SSH_PASSWORD = sys.argv[1]
ADMIN_MOBILE = "09051770091"
USER_MOBILE = "09378156704"
NEW_PASSWORD = "".join(
    secrets.choice(string.ascii_letters + string.digits) for _ in range(12)
)

remote_script = f"""<?php
require '/var/www/playnova/PlayNova/vendor/autoload.php';
$app = require '/var/www/playnova/PlayNova/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

use App\\Models\\User;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Str;

$password = '{NEW_PASSWORD}';
$hash = Hash::make($password);

function upsertUser(string $mobile, string $hash, bool $isAdmin, string $label): array {{
    $user = User::query()->where('mobile', $mobile)->first();
    $created = false;

    if (! $user) {{
        $baseUsername = ($isAdmin ? 'admin_' : 'user_') . substr($mobile, -4);
        $username = $baseUsername;
        $suffix = 1;
        while (User::query()->where('username', $username)->exists()) {{
            $username = $baseUsername . $suffix;
            $suffix++;
        }}

        $codBase = ($isAdmin ? 'admin_' : 'user_') . Str::lower(Str::random(8));
        while (User::codIdIsTaken($codBase)) {{
            $codBase = ($isAdmin ? 'admin_' : 'user_') . Str::lower(Str::random(8));
        }}

        $user = User::create([
            'name' => $username,
            'username' => $username,
            'email' => null,
            'mobile' => $mobile,
            'password' => $hash,
            'cod_id' => $codBase,
            'referral_code' => User::generateReferralCode(),
            'is_admin' => $isAdmin,
        ]);
        $created = true;
    }} else {{
        $user->password = $hash;
        if ($isAdmin) {{
            $user->is_admin = true;
        }}
        $user->save();
    }}

    return [
        'role' => $label,
        'created' => $created,
        'id' => $user->id,
        'username' => $user->username,
        'mobile' => $user->mobile,
        'is_admin' => (bool) $user->is_admin,
    ];
}}

$result = [
    'password' => $password,
    'accounts' => [
        upsertUser('{ADMIN_MOBILE}', $hash, true, 'admin'),
        upsertUser('{USER_MOBILE}', $hash, false, 'user'),
    ],
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
"""

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=SSH_PASSWORD, timeout=30)

sftp = client.open_sftp()
with sftp.file("/tmp/set_users_pw.php", "w") as f:
    f.write(remote_script)
sftp.close()

_, stdout, stderr = client.exec_command("php /tmp/set_users_pw.php && rm -f /tmp/set_users_pw.php")
out = stdout.read().decode("utf-8", errors="replace").strip()
err = stderr.read().decode("utf-8", errors="replace").strip()
client.close()

if err and not out:
    sys.stdout.buffer.write(f"ERROR: {err}\n".encode("utf-8", errors="replace"))
    sys.exit(1)

sys.stdout.buffer.write((out + "\n").encode("utf-8", errors="replace"))
