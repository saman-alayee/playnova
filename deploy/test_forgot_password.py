#!/usr/bin/env python3
import paramiko
import sys

HOST = "193.105.234.54"
USER = "root"
PASSWORD = sys.argv[1]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASSWORD, timeout=30)

remote_php = r"""<?php
require '/var/www/playnova/PlayNova/vendor/autoload.php';
$app = require '/var/www/playnova/PlayNova/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/api/v1/auth/forgot-password', 'POST', ['mobile' => '09051770091']);
$request->headers->set('Accept', 'application/json');
$response = $app->handle($request);
echo "HTTP " . $response->getStatusCode() . "\n";
echo $response->getContent() . "\n";

// also test SMS directly
$code = 123456;
$result = App\Jobs\SendOtpSmsJob::sendNow('09051770091', $code, 'reset');
echo "SMS_TEST: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
"""

sftp = client.open_sftp()
with sftp.file("/tmp/test_forgot.php", "w") as f:
    f.write(remote_php)
sftp.close()

_, stdout, stderr = client.exec_command("php /tmp/test_forgot.php && rm -f /tmp/test_forgot.php")
out = stdout.read().decode("utf-8", errors="replace")
err = stderr.read().decode("utf-8", errors="replace")
sys.stdout.buffer.write((out or err).encode("utf-8", errors="replace"))
client.close()
