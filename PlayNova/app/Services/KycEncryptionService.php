<?php

namespace App\Services;

use RuntimeException;

class KycEncryptionService
{
    protected function key(): string
    {
        $raw = env('KYC_ENCRYPTION_KEY') ?: config('app.key');

        if (! $raw) {
            throw new RuntimeException('کلید رمزنگاری KYC تنظیم نشده است.');
        }

        if (str_starts_with($raw, 'base64:')) {
            $raw = base64_decode(substr($raw, 7));
        }

        return hash('sha256', $raw, true);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $this->key(), OPENSSL_RAW_DATA, $iv);

        if ($cipher === false) {
            throw new RuntimeException('رمزنگاری ناموفق بود.');
        }

        return base64_encode($iv . $cipher);
    }

    public function decrypt(string $payload): string
    {
        $decoded = base64_decode($payload, true);

        if ($decoded === false || strlen($decoded) < 17) {
            throw new RuntimeException('داده رمزنگاری‌شده نامعتبر است.');
        }

        $iv = substr($decoded, 0, 16);
        $cipher = substr($decoded, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $this->key(), OPENSSL_RAW_DATA, $iv);

        if ($plain === false) {
            throw new RuntimeException('رمزگشایی ناموفق بود.');
        }

        return $plain;
    }

    public function encryptFile(string $sourcePath, string $destPath): void
    {
        $contents = file_get_contents($sourcePath);

        if ($contents === false) {
            throw new RuntimeException('خواندن فایل ناموفق بود.');
        }

        $dir = dirname($destPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($destPath, $this->encrypt($contents));
    }

    public function decryptToTemp(string $encryptedPath): string
    {
        $payload = file_get_contents($encryptedPath);

        if ($payload === false) {
            throw new RuntimeException('خواندن فایل رمزنگاری‌شده ناموفق بود.');
        }

        $plain = $this->decrypt($payload);
        $temp = tempnam(sys_get_temp_dir(), 'kyc_');
        file_put_contents($temp, $plain);

        return $temp;
    }
}
