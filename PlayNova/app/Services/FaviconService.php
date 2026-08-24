<?php

namespace App\Services;

class FaviconService
{
    public static function publicRoot(): string
    {
        return dirname(base_path());
    }

    public static function regenerateFromFile(string $sourcePath, ?string $publicRoot = null): bool
    {
        $publicRoot = $publicRoot ?? self::publicRoot();

        if (! is_file($sourcePath) || ! extension_loaded('gd')) {
            return false;
        }

        $contents = @file_get_contents($sourcePath);
        if ($contents === false) {
            return false;
        }

        $src = @imagecreatefromstring($contents);
        if (! $src) {
            return false;
        }

        $width = imagesx($src);
        $height = imagesy($src);

        foreach ([16, 32, 48, 96, 192] as $size) {
            $dest = imagecreatetruecolor($size, $size);
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
            imagefilledrectangle($dest, 0, 0, $size, $size, $transparent);
            imagecopyresampled($dest, $src, 0, 0, 0, 0, $size, $size, $width, $height);
            imagepng($dest, "{$publicRoot}/favicon-{$size}x{$size}.png", 9);
            imagedestroy($dest);
        }

        @copy("{$publicRoot}/favicon-48x48.png", "{$publicRoot}/favicon.png");

        imagedestroy($src);

        return true;
    }
}
