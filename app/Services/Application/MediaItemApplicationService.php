<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Application;

use BSPhotoGalerie\Core\Request;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Services\Domain\MediaAdminService;
use BSPhotoGalerie\Services\Media\ExifExtractor;
use BSPhotoGalerie\Services\Media\MediaAssetService;

/**
 * Anwendungsfall: Einzelmedium im Admin bearbeiten, Titel inline, löschen.
 */
final class MediaItemApplicationService
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaAssetService $mediaAssetService,
        private MediaAdminService $mediaAdmin,
        private string $projectRoot
    ) {
    }

    public function updateMetadataFromPost(int $id, Request $request): bool
    {
        $media = $this->mediaRepository->findById($id);
        if ($media === null) {
            return false;
        }

        $title = trim((string) $request->post('title', ''));
        $description = (string) $request->post('description', '');
        $categoryId = $this->mediaAdmin->resolveCategoryId($request->post('category_id'));
        $visible = $request->post('is_visible') === '1';

        $this->mediaRepository->updateMetadata(
            $id,
            $title !== '' ? $title : $media->title,
            $description,
            $categoryId,
            $visible
        );

        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->mediaAssetService->deleteCompletely($id);
    }

    /**
     * @return array{ok: true, mediaId: int}|array{ok: false, error: string}
     */
    public function inlineTitleFromRequest(Request $request): array
    {
        $idRaw = $request->post('id');
        $titleRaw = $request->post('title');

        if (! is_string($idRaw) || ! ctype_digit($idRaw)) {
            return ['ok' => false, 'error' => 'Ungültige Anfrage.'];
        }

        $mid = (int) $idRaw;
        $media = $this->mediaRepository->findById($mid);
        if ($media === null) {
            return ['ok' => false, 'error' => 'Medium nicht gefunden.'];
        }

        $title = is_string($titleRaw) ? trim($titleRaw) : '';
        $this->mediaRepository->updateTitle($mid, $title !== '' ? $title : $media->title);

        return ['ok' => true, 'mediaId' => $mid];
    }

    /**
     * Liest EXIF erneut von der gespeicherten Datei und schreibt `exif_json` (null, wenn kein EXIF/MIME).
     *
     * @return array{ok: true, had_exif: bool}|array{ok: false, error: string}
     */
    public function refreshExifFromDisk(int $id): array
    {
        $media = $this->mediaRepository->findById($id);
        if ($media === null) {
            return ['ok' => false, 'error' => 'Medium nicht gefunden.'];
        }

        $absolute = rtrim($this->projectRoot, '/') . '/public/' . ltrim($media->storagePath, '/');
        if (! is_file($absolute) || ! is_readable($absolute)) {
            return ['ok' => false, 'error' => 'Datei auf dem Server nicht lesbar.'];
        }

        $json = (new ExifExtractor())->extractAsJson($absolute, $media->mimeType);
        $this->mediaRepository->updateExifJson($id, $json);

        return ['ok' => true, 'had_exif' => $json !== null];
    }

    /**
     * EXIF für mehrere Medien neu einlesen (max. 200 IDs).
     *
     * @param list<int|string> $ids
     *
     * @return array{with_exif: int, without_exif: int, failed: int}
     */
    public function refreshExifForIds(array $ids): array
    {
        $clean = [];
        foreach ($ids as $v) {
            $id = is_int($v) ? $v : (is_string($v) && ctype_digit($v) ? (int) $v : 0);
            if ($id > 0) {
                $clean[$id] = true;
            }
        }
        $list = array_slice(array_keys($clean), 0, 200);

        $with = 0;
        $without = 0;
        $failed = 0;
        foreach ($list as $id) {
            $r = $this->refreshExifFromDisk($id);
            if (! $r['ok']) {
                ++$failed;

                continue;
            }
            if ($r['had_exif']) {
                ++$with;
            } else {
                ++$without;
            }
        }

        return ['with_exif' => $with, 'without_exif' => $without, 'failed' => $failed];
    }
}
