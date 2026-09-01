<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

trait HandlesUploadLimits
{
    protected function uploadLimitError(Request $request, string $field): ?string
    {
        $file = $request->file($field);

        if ($file instanceof UploadedFile) {
            if ($file->getError() === UPLOAD_ERR_OK) {
                return null;
            }

            return $this->uploadPhpErrorMessage($file->getError());
        }

        $contentLength = (int) $request->header('Content-Length', 0);
        if ($contentLength <= 0) {
            return null;
        }

        if ($request->isMethod('POST') && ! $request->hasFile($field)) {
            return sprintf(
                'حجم فایل از سقف مجاز سرور (%s) بیشتر است. لطفاً تصویر کوچک‌تری ارسال کنید.',
                $this->formatIniBytes(min($this->iniBytes('upload_max_filesize'), $this->iniBytes('post_max_size'))),
            );
        }

        return null;
    }

    protected function uploadPhpErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                'حجم فایل از سقف مجاز (%s) بیشتر است.',
                $this->formatIniBytes($this->iniBytes('upload_max_filesize')),
            ),
            UPLOAD_ERR_PARTIAL => 'فایل به‌طور ناقص بارگذاری شد. لطفاً دوباره تلاش کنید.',
            UPLOAD_ERR_NO_FILE => 'فایلی انتخاب نشده است.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'خطای سرور هنگام دریافت فایل. لطفاً بعداً دوباره تلاش کنید.',
            default => 'بارگذاری فایل ناموفق بود.',
        };
    }

    protected function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }

    protected function formatIniBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            $mb = $bytes / (1024 * 1024);

            return rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.') . ' مگابایت';
        }

        if ($bytes >= 1024) {
            return (int) round($bytes / 1024) . ' کیلوبایت';
        }

        return $bytes . ' بایت';
    }
}
