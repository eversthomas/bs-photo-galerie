<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Application;

use BSPhotoGalerie\Core\Request;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Services\Domain\MediaAdminService;
use BSPhotoGalerie\Services\Media\MediaAssetService;

/**
 * Anwendungsfall: Einzelmedium im Admin bearbeiten, Titel inline, löschen.
 */
final class MediaItemApplicationService
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaAssetService $mediaAssetService,
        private MediaAdminService $mediaAdmin
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
}
