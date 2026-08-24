<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use RuntimeException;

trait HandlesKycDocuments
{
    protected function kycCanCompress(): bool
    {
        return extension_loaded('gd') && function_exists('imagejpeg');
    }

    protected function kycUploadMaxKilobytes(): int
    {
        return $this->kycCanCompress() ? 10240 : 2048;
    }

    protected function prepareKycImage(UploadedFile $file): string
    {
        $sourcePath = $file->getRealPath();

        if (! $sourcePath) {
            throw new RuntimeException('فایل نامعتبر است.');
        }

        if (! $this->kycCanCompress()) {
            if ($file->getSize() > 2097152) {
                throw new RuntimeException('حداکثر حجم تصویر ۲ مگابایت است.');
            }

            return $sourcePath;
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new RuntimeException('حداکثر حجم تصویر برای آپلود ۱۰ مگابایت است.');
        }

        return $this->compressKycImage($sourcePath);
    }

    protected function compressKycImage(string $sourcePath): string
    {
        $info = @getimagesize($sourcePath);

        if (! is_array($info)) {
            throw new RuntimeException('فایل تصویر قابل پردازش نیست.');
        }

        [$width, $height, $type] = $info;
        $image = $this->loadKycImage($sourcePath, $type);

        if (! $image) {
            throw new RuntimeException('فرمت تصویر پشتیبانی نمی‌شود. از JPG یا PNG استفاده کنید.');
        }

        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            if (! empty($exif['Orientation'])) {
                $image = $this->applyKycOrientation($image, (int) $exif['Orientation']);
                $width = imagesx($image);
                $height = imagesy($image);
            }
        }

        $maxWidth = 1920;
        $maxHeight = 1920;

        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = max(1, (int) round($width * $ratio));
            $newHeight = max(1, (int) round($height * $ratio));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'kyc_img_') . '.jpg';
        $quality = 85;
        $size = 0;

        do {
            imagejpeg($image, $tempPath, $quality);
            $size = (int) filesize($tempPath);

            if ($size <= 1800000 || $quality <= 55) {
                break;
            }

            $quality -= 5;
        } while ($quality >= 55);

        imagedestroy($image);

        if ($size > 2097152) {
            @unlink($tempPath);
            throw new RuntimeException('پس از فشرده‌سازی، حجم تصویر هنوز بیش از ۲ مگابایت است. لطفاً تصویر کوچک‌تری ارسال کنید.');
        }

        return $tempPath;
    }

    protected function loadKycImage(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    protected function applyKycOrientation($image, int $orientation)
    {
        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
