#!/usr/bin/env python3
import paramiko
import sys
from pathlib import Path

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]
ACTION = sys.argv[2] if len(sys.argv) > 2 else "deploy-and-test"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

local = Path(__file__).resolve().parent.parent / "PlayNova/app/Jobs/SendOtpSmsJob.php"
remote = "/var/www/playnova/PlayNova/app/Jobs/SendOtpSmsJob.php"

sftp = client.open_sftp()
sftp.put(str(local), remote)
sftp.close()

test_php = r"""<?php
require '/var/www/playnova/PlayNova/vendor/autoload.php';
$app = require '/var/www/playnova/PlayNova/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/api/v1/auth/forgot-password', 'POST', ['mobile' => '09051770091']);
$request->headers->set('Accept', 'application/json');
$response = $app->handle($request);
echo "FORGOT: HTTP " . $response->getStatusCode() . " " . $response->getContent() . "\n";

$sms = App\Jobs\SendOtpSmsJob::sendNow('09051770091', 999888, 'reset');
echo "SMS: " . json_encode($sms, JSON_UNESCAPED_UNICODE) . "\n";
"""

with sftp.open("/tmp/test_forgot2.php", "w") as f:
    f.write(test_php)

_, stdout, stderr = client.exec_command("php /tmp/test_forgot2.php && rm -f /tmp/test_forgot2.php")
out = stdout.read().decode("utf-8", errors="replace")
err = stderr.read().decode("utf-8", errors="replace")
sys.stdout.buffer.write((out or err).encode("utf-8", errors="replace"))
client.close()
