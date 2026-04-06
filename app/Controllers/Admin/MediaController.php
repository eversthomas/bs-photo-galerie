<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Services\AuthService;
use BSPhotoGalerie\Services\Domain\MediaAdminService;
use BSPhotoGalerie\Services\Media\MediaAssetService;
use BSPhotoGalerie\Services\Media\MediaUploadService;
use BSPhotoGalerie\Services\Media\UploadedFiles;

/**
 * Medienliste, Upload, Bearbeiten, Sortierung, Löschen.
 */
final class MediaController extends BaseController
{
    public function __construct(
        HttpContext $http,
        private MediaRepository $mediaRepository,
        private CategoryRepository $categoryRepository,
        private AuthService $auth,
        private MediaUploadService $mediaUploadService,
        private MediaAssetService $mediaAssetService,
        private MediaAdminService $mediaAdmin
    ) {
        parent::__construct($http);
    }

    public function index(): void
    {
        $period = $this->mediaAdmin->normalizePeriod((string) ($this->http->request()->query('period', 'all') ?? 'all'));
        $items = $this->mediaRepository->listByUploadPeriod($period, 200, 0);
        $categories = $this->categoryRepository->listAllOrdered();
        $this->render(
            'admin/media/index',
            [
                'title' => 'Medien',
                'items' => $items,
                'categories' => $categories,
                'mediaPeriod' => $period,
                'mediaPeriodLabel' => MediaAdminService::PERIOD_LABELS[$period] ?? 'Alle',
                'mediaPeriodLabels' => MediaAdminService::PERIOD_LABELS,
                'user' => $this->auth->user(),
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function uploadForm(): void
    {
        $categories = $this->categoryRepository->listAllOrdered();
        $this->render(
            'admin/media/upload',
            [
                'title' => 'Hochladen',
                'categories' => $categories,
                'user' => $this->auth->user(),
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function upload(): void
    {
        $files = UploadedFiles::normalizeList($this->http->request()->files()['images'] ?? null);
        if ($files === []) {
            Flash::set('error', 'Bitte mindestens eine Bilddatei auswählen.');
            $this->http->redirect('/admin/media/upload');
        }

        $categoryId = $this->mediaAdmin->resolveCategoryId($this->http->request()->post('category_id'));
        $titleBase = $this->http->request()->post('title');
        $titleBase = is_string($titleBase) ? trim($titleBase) : null;
        if ($titleBase === '') {
            $titleBase = null;
        }

        $result = $this->mediaUploadService->processMany($files, $categoryId, $titleBase);

        if ($result['imported'] > 0 && $result['errors'] === []) {
            Flash::set('success', $result['imported'] . ' Datei(en) erfolgreich hochgeladen.');
        } elseif ($result['imported'] > 0) {
            Flash::set('success', $result['imported'] . ' Datei(en) hochgeladen.');
            Flash::add('info', 'Hinweise: ' . implode('; ', $result['errors']));
        } else {
            Flash::set('error', implode(' ', $result['errors']));
        }

        $this->http->redirect('/admin/media');
    }

    public function edit(string $id): void
    {
        $mid = (int) $id;
        $media = $this->mediaRepository->findById($mid);
        if ($media === null) {
            Flash::set('error', 'Medium nicht gefunden.');
            $this->http->redirect('/admin/media');
        }

        $categories = $this->categoryRepository->listAllOrdered();
        $this->render(
            'admin/media/edit',
            [
                'title' => 'Medium bearbeiten',
                'media' => $media,
                'categories' => $categories,
                'user' => $this->auth->user(),
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function update(string $id): void
    {
        $mid = (int) $id;
        $media = $this->mediaRepository->findById($mid);
        if ($media === null) {
            Flash::set('error', 'Medium nicht gefunden.');
            $this->http->redirect('/admin/media');
        }

        $title = trim((string) $this->http->request()->post('title', ''));
        $description = (string) $this->http->request()->post('description', '');
        $categoryId = $this->mediaAdmin->resolveCategoryId($this->http->request()->post('category_id'));
        $visible = $this->http->request()->post('is_visible') === '1';

        $this->mediaRepository->updateMetadata(
            $mid,
            $title !== '' ? $title : $media->title,
            $description,
            $categoryId,
            $visible
        );

        Flash::set('success', 'Änderungen gespeichert.');
        $this->http->redirect('/admin/media/' . $mid . '/edit');
    }

    public function destroy(string $id): void
    {
        $mid = (int) $id;
        if (! $this->mediaAssetService->deleteCompletely($mid)) {
            Flash::set('error', 'Medium nicht gefunden.');
            $this->http->redirect('/admin/media');
        }

        Flash::set('success', 'Medium gelöscht.');
        $this->http->redirect('/admin/media');
    }

    public function reorder(): void
    {
        $period = $this->mediaAdmin->normalizePeriod((string) $this->http->request()->post('period', 'all'));
        $result = $this->mediaAdmin->reorderFromPost($period, $this->http->request()->post('order'));

        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->http->redirect('/admin/media' . $result['query']);

            return;
        }

        Flash::set('success', 'Reihenfolge gespeichert.');
        $this->http->redirect('/admin/media' . $result['query']);
    }

    public function bulkCategory(): void
    {
        $periodRaw = (string) $this->http->request()->post('period', 'all');
        $ids = $this->mediaAdmin->parseBulkIdsFromPost($this->http->request()->post('ids'));

        $result = $this->mediaAdmin->bulkAssignCategoryFromPost(
            $ids,
            $this->http->request()->post('bulk_category_id'),
            $periodRaw
        );

        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->http->redirect('/admin/media' . $result['query']);

            return;
        }

        $updated = $result['updated'];
        $categoryId = $result['categoryId'];
        $q = $result['query'];

        if ($updated < 1) {
            Flash::set('error', 'Keine Einträge geändert.');
        } elseif ($categoryId === null) {
            Flash::set(
                'success',
                $updated === 1
                    ? 'Ein Bild ist jetzt ohne Kategorie.'
                    : $updated . ' Bilder sind jetzt ohne Kategorie.'
            );
        } else {
            Flash::set(
                'success',
                $updated === 1
                    ? 'Ein Bild wurde der Kategorie zugewiesen.'
                    : $updated . ' Bilder wurden der Kategorie zugewiesen.'
            );
        }
        $this->http->redirect('/admin/media' . $q);
    }

    public function inlineTitle(): void
    {
        $idRaw = $this->http->request()->post('id');
        $titleRaw = $this->http->request()->post('title');

        if (! is_string($idRaw) || ! ctype_digit($idRaw)) {
            Flash::set('error', 'Ungültige Anfrage.');
            $this->http->redirect('/admin/media');
        }

        $mid = (int) $idRaw;
        $media = $this->mediaRepository->findById($mid);
        if ($media === null) {
            Flash::set('error', 'Medium nicht gefunden.');
            $this->http->redirect('/admin/media');
        }

        $title = is_string($titleRaw) ? trim($titleRaw) : '';
        $this->mediaRepository->updateTitle($mid, $title !== '' ? $title : $media->title);

        Flash::set('success', 'Titel aktualisiert.');
        $this->http->redirect('/admin/media');
    }
}
