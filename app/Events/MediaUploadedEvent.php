<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Events;

/**
 * Wird ausgelöst, nachdem ein Bild inkl. DB-Eintrag und Thumbnail erfolgreich persistiert wurde
 * (HTTP-Upload und Import über {@see \BSPhotoGalerie\Services\Media\MediaUploadService}).
 */
final readonly class MediaUploadedEvent
{
    public function __construct(
        public int $mediaId,
        public string $storagePath,
        public ?int $categoryId
    ) {
    }
}
