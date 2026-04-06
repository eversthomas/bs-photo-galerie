<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Services\AuthService;
use BSPhotoGalerie\Services\Application\MediaItemApplicationService;
use BSPhotoGalerie\Services\Domain\MediaAdminService;
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
        private MediaAdminService $mediaAdmin,
        private MediaItemApplicationService $mediaItems
    ) {
        parent::__construct($http);
    }

    public function index(): void
    {
        $period = $this->mediaAdmin->normalizePeriod((string) ($this->http->request()->query('period', 'all') ?? 'all'));
        $listSort = $this->mediaAdmin->normalizeListSort((string) $this->http->request()->query('sort', ''));
        $listDir = $this->mediaAdmin->normalizeListDir((string) $this->http->request()->query('dir', 'desc'));
        $items = $this->mediaRepository->listByUploadPeriod($period, 200, 0, $listSort, $listDir);
        $categories = $this->categoryRepository->listAllOrdered();

        $periodQueries = [];
        foreach (array_keys(MediaAdminService::PERIOD_LABELS) as $pkey) {
            $periodQueries[$pkey] = $this->mediaAdmin->adminMediaIndexQuery($pkey, $listSort, $listDir);
        }

        $sortQueries = [
            'manual' => $this->mediaAdmin->adminMediaIndexQuery($period, 'manual', 'desc'),
            'upload_desc' => $this->mediaAdmin->adminMediaIndexQuery($period, 'upload', 'desc'),
            'upload_asc' => $this->mediaAdmin->adminMediaIndexQuery($period, 'upload', 'asc'),
            'captured_desc' => $this->mediaAdmin->adminMediaIndexQuery($period, 'captured', 'desc'),
            'captured_asc' => $this->mediaAdmin->adminMediaIndexQuery($period, 'captured', 'asc'),
        ];

        $this->render(
            'admin/media/index',
            [
                'title' => 'Medien',
                'items' => $items,
                'categories' => $categories,
                'mediaPeriod' => $period,
                'mediaPeriodLabel' => MediaAdminService::PERIOD_LABELS[$period] ?? 'Alle',
                'mediaPeriodLabels' => MediaAdminService::PERIOD_LABELS,
                'mediaPeriodQueries' => $periodQueries,
                'mediaListSort' => $listSort,
                'mediaListDir' => $listDir,
                'mediaSortQueries' => $sortQueries,
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
        if (! $this->mediaItems->updateMetadataFromPost($mid, $this->http->request())) {
            Flash::set('error', 'Medium nicht gefunden.');
            $this->http->redirect('/admin/media');
        }

        Flash::set('success', 'Änderungen gespeichert.');
        $this->http->redirect('/admin/media/' . $mid . '/edit');
    }

    public function refreshExif(string $id): void
    {
        $mid = (int) $id;
        $result = $this->mediaItems->refreshExifFromDisk($mid);
        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->http->redirect('/admin/media/' . $mid . '/edit');
        }

        if ($result['had_exif']) {
            Flash::set('success', 'EXIF-Metadaten wurden neu eingelesen und gespeichert.');
        } else {
            Flash::set(
                'info',
                'Es wurden keine EXIF-Daten gefunden (z. B. PNG/WebP/GIF oder EXIF-Erweiterung fehlt). Der Eintrag wurde aktualisiert.'
            );
        }
        $this->http->redirect('/admin/media/' . $mid . '/edit');
    }

    public function destroy(string $id): void
    {
        $mid = (int) $id;
        if (! $this->mediaItems->deleteById($mid)) {
            Flash::set('error', 'Medium nicht gefunden.');
            $this->http->redirect('/admin/media');
        }

        Flash::set('success', 'Medium gelöscht.');
        $this->http->redirect('/admin/media');
    }

    public function reorder(): void
    {
        $period = $this->mediaAdmin->normalizePeriod((string) $this->http->request()->post('period', 'all'));
        $result = $this->mediaAdmin->reorderFromPost(
            $period,
            $this->http->request()->post('order'),
            (string) $this->http->request()->post('list_sort', ''),
            (string) $this->http->request()->post('list_dir', 'desc')
        );

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
            $periodRaw,
            (string) $this->http->request()->post('list_sort', ''),
            (string) $this->http->request()->post('list_dir', 'desc')
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

    public function bulkRefreshExif(): void
    {
        $periodRaw = (string) $this->http->request()->post('period', 'all');
        $period = $this->mediaAdmin->normalizePeriod($periodRaw);
        $q = $this->mediaAdmin->adminMediaIndexQuery(
            $period,
            $this->mediaAdmin->normalizeListSort((string) $this->http->request()->post('list_sort', '')),
            $this->mediaAdmin->normalizeListDir((string) $this->http->request()->post('list_dir', 'desc'))
        );
        $ids = $this->mediaAdmin->parseBulkIdsFromPost($this->http->request()->post('ids'));

        if ($ids === []) {
            Flash::set('error', 'Bitte mindestens ein Bild auswählen.');
            $this->http->redirect('/admin/media' . $q);

            return;
        }

        $stats = $this->mediaItems->refreshExifForIds($ids);
        $parts = [
            $stats['with_exif'] . ' mit EXIF',
            $stats['without_exif'] . ' ohne EXIF',
        ];
        if ($stats['failed'] > 0) {
            $parts[] = $stats['failed'] . ' fehlgeschlagen';
        }
        Flash::set('success', 'EXIF neu eingelesen: ' . implode(', ', $parts) . '.');
        if ($stats['failed'] > 0) {
            Flash::add('info', 'Fehlgeschlagen: Medium nicht gefunden oder Datei nicht lesbar.');
        }
        $this->http->redirect('/admin/media' . $q);
    }

    public function inlineTitle(): void
    {
        $result = $this->mediaItems->inlineTitleFromRequest($this->http->request());
        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->http->redirect('/admin/media');
        }

        Flash::set('success', 'Titel aktualisiert.');
        $this->http->redirect('/admin/media');
    }
}
