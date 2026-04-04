<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Media;

use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Erzeugt JPEG-Vorschaubilder mit Intervention Image (GD).
 */
final class ThumbnailGenerator
{
    public function __construct(
        private ImageManager $imageManager,
        private int $maxWidth
    ) {
    }

    public function createJpegThumbnail(string $sourceAbsolute, string $targetAbsolute): void
    {
        if ($this->maxWidth < 32) {
            throw new RuntimeException('Ungültige Vorschaubreite.');
        }

        $dir = dirname($targetAbsolute);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Thumbnail-Ordner konnte nicht angelegt werden.');
        }

        try {
            $image = $this->imageManager->read($sourceAbsolute);
            $image->scaleDown(width: $this->maxWidth);
            $image->encode(new JpegEncoder(quality: 82, strip: true))->save($targetAbsolute);
        } catch (\Throwable $e) {
            throw new RuntimeException('Vorschaubild fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }

        if (! is_file($targetAbsolute)) {
            throw new RuntimeException('Vorschaubild wurde nicht geschrieben.');
        }
    }
}
