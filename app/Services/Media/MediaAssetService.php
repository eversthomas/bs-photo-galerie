<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Media;

use BSPhotoGalerie\Models\MediaRepository;

/**
 * Löscht Medien inkl. Datei und Vorschaubild.
 */
final class MediaAssetService
{
    public function __construct(
        private string $projectRoot,
        private MediaRepository $mediaRepository
    ) {
    }

    public function deleteCompletely(int $id): bool
    {
        $media = $this->mediaRepository->findById($id);
        if ($media === null) {
            return false;
        }

        $rel = trim(str_replace('\\', '/', $media->storagePath), '/');
        if ($rel !== '' && ! str_contains($rel, '..')) {
            $full = $this->projectRoot . '/public/' . $rel;
            if (is_file($full)) {
                @unlink($full);
            }
        }

        $thumb = $this->projectRoot . '/storage/thumbnails/' . $id . '.jpg';
        if (is_file($thumb)) {
            @unlink($thumb);
        }

        $this->mediaRepository->deleteById($id);

        return true;
    }
}
