<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Models;

/**
 * Ein gespeichertes Bild bzw. Mediendatei.
 */
final class Media
{
    public function __construct(
        public int $id,
        public ?int $categoryId,
        public string $filename,
        /** Relativ zu public/: z. B. uploads/2026/04/xyz.jpg */
        public string $storagePath,
        public string $fileHash,
        public string $mimeType,
        public ?int $width,
        public ?int $height,
        public string $title,
        public string $description,
        public bool $isVisible,
        public string $createdAt
    ) {
    }
}
