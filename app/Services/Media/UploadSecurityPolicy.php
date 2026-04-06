<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Media;

use BSPhotoGalerie\Config\MediaSettings;

/**
 * Zentrale Upload-/Bild-Limits für sicherheitsrelevante Entscheidungen (delegiert an {@see MediaSettings}).
 */
final class UploadSecurityPolicy
{
    public function __construct(
        private MediaSettings $media
    ) {
    }

    public function maxUploadBytes(): int
    {
        return $this->media->maxUploadBytes();
    }

    public function thumbnailMaxWidth(): int
    {
        return $this->media->thumbnailMaxWidth();
    }

    public function maxImagePixels(): int
    {
        return $this->media->maxImagePixels();
    }

    /**
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        return $this->media->allowedMimeTypes();
    }
}
