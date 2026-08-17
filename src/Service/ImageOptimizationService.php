<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageOptimizationService
{
    public function __construct(private readonly FilesystemOperator $galleryStorage)
    {
    }

    /**
     * Optimizes an image (max 1920x1080, WebP/JPEG conversion) and saves it to storage.
     * Returns the relative storage path (e.g. 'gallery/campaign-abc-123.webp').
     */
    public function optimizeAndSave(UploadedFile $file, string $prefix = 'photo'): string
    {
        $mime = $file->getMimeType();
        $sourcePath = $file->getRealPath();

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            default => null,
        };

        $maxWidth = 1920;
        $maxHeight = 1080;

        if ($image !== null) {
            $width = imagesx($image);
            $height = imagesy($image);

            if ($width > $maxWidth || $height > $maxHeight) {
                $ratio = min($maxWidth / $width, $maxHeight / $height);
                $newWidth = max(1, (int) ($width * $ratio));
                $newHeight = max(1, (int) ($height * $ratio));

                $newImage = imagecreatetruecolor($newWidth, $newHeight);

                if ($mime === 'image/png' || $mime === 'image/webp' || $mime === 'image/gif') {
                    imagealphablending($newImage, false);
                    imagesavealpha($newImage, true);
                    $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                    imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
                }

                imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $newImage;
            }

            $tempPath = sys_get_temp_dir() . '/' . uniqid('cc_opt_', true);
            $ext = 'webp';

            if (function_exists('imagewebp')) {
                $tempPath .= '.webp';
                imagewebp($image, $tempPath, 85);
            } elseif ($mime === 'image/png') {
                $ext = 'png';
                $tempPath .= '.png';
                imagepng($image, $tempPath, 8);
            } else {
                $ext = 'jpg';
                $tempPath .= '.jpg';
                imagejpeg($image, $tempPath, 85);
            }

            imagedestroy($image);
        } else {
            // If GD couldn't decode, use original file
            $tempPath = $sourcePath;
            $ext = strtolower($file->guessExtension() ?? 'jpg');
        }

        $safePrefix = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim($prefix))) ?: 'photo';
        $storagePath = sprintf('gallery/%s-%s.%s', $safePrefix, bin2hex(random_bytes(6)), $ext);

        $stream = fopen($tempPath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to read temporary image file for storage.');
        }

        try {
            $this->galleryStorage->writeStream($storagePath, $stream);
        } finally {
            fclose($stream);
            if ($tempPath !== $sourcePath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        return $storagePath;
    }

    public function delete(string $storagePath): void
    {
        if ($this->galleryStorage->fileExists($storagePath)) {
            $this->galleryStorage->delete($storagePath);
        }
    }
}
