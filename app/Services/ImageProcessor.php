<?php

namespace App\Services;

use RuntimeException;

/**
 * Re-encodes uploads with GD: strips EXIF (including GPS), fixes phone-photo
 * rotation, limits dimensions and produces a thumbnail. Re-encoding also neutralises
 * files that are valid images but carry hidden payloads.
 */
class ImageProcessor
{
    public const MAX_WIDTH = 1600;
    public const THUMB_WIDTH = 480;

    public function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    /** @return array{main: string, thumb: string}|null null when GD is not installed */
    public function process(string $binary): ?array
    {
        if (! $this->available()) {
            return null;
        }

        @ini_set('memory_limit', '256M');

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new RuntimeException('The image could not be read.');
        }

        $source = $this->applyOrientation($source, $binary);

        try {
            return [
                'main' => $this->encode($source, self::MAX_WIDTH),
                'thumb' => $this->encode($source, self::THUMB_WIDTH),
            ];
        } finally {
            imagedestroy($source);
        }
    }

    private function encode(\GdImage $source, int $maxWidth): string
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $newW = min($w, $maxWidth);
        $newH = (int) round($h * ($newW / $w));

        // Flatten onto white so transparent PNGs do not turn black as JPEG.
        $canvas = imagecreatetruecolor($newW, $newH);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $w, $h);

        ob_start();
        imagejpeg($canvas, null, 82);
        $bytes = (string) ob_get_clean();
        imagedestroy($canvas);

        return $bytes;
    }

    private function applyOrientation(\GdImage $image, string $binary): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated ?: $image;
    }
}
