<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Services\Domain\MediaAdminService;
use BSPhotoGalerie\Services\Media\UploadedFiles;

/**
 * Medienliste, Upload, Bearbeiten, Sortierung, Löschen.
 */
final class MediaController extends BaseController
{
    public function index(): void
    {
        $svc = $this->app->mediaAdminService();
        $period = $svc->normalizePeriod((string) ($this->app->request()->query('period', 'all') ?? 'all'));
        $items = $this->app->mediaRepository()->listByUploadPeriod($period, 200, 0);
        $categories = $this->app->categoryRepository()->listAllOrdered();
        $this->render(
            'admin/media/index',
            [
                'title' => 'Medien',
                'items' => $items,
                'categories' => $categories,
                'mediaPeriod' => $period,
                'mediaPeriodLabel' => MediaAdminService::PERIOD_LABELS[$period] ?? 'Alle',
                'mediaPeriodLabels' => MediaAdminService::PERIOD_LABELS,
                'user' => $this->app->auth()->user(),
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function uploadForm(): void
    {
        $categories = $this->app->categoryRepository()->listAllOrdered();
        $this->render(
            'admin/media/upload',
            [
                'title' => 'Hochladen',
                'categories' => $categories,
                'user' => $this->app->auth()->user(),
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function upload(): void
    {
        $files = UploadedFiles::normalizeList($this->app->request()->files()['images'] ?? null);
        if ($files === []) {
            Flash::set('error', 'Bitte mindestens eine Bilddatei auswählen.');
            $this->app->redirect('/admin/media/upload');
        }

        $svc = $this->app->mediaAdminService();
        $categoryId = $svc->resolveCategoryId($this->app->request()->post('category_id'));
        $titleBase = $this->app->request()->post('title');
        $titleBase = is_string($titleBase) ? trim($titleBase) : null;
        if ($titleBase === '') {
            $titleBase = null;
        }

        $result = $this->app->mediaUploadService()->processMany($files, $categoryId, $titleBase);

        if ($result['imported'] > 0 && $result['errors'] === []) {
            Flash::set('success', $result['imported'] . ' Datei(en) erfolgreich hochgeladen.');
        } elseif ($result['imported'] > 0) {
            Flash::set('success', $result['imported'] . ' Datei(en) hochgeladen.');
            Flash::add('info', 'Hinweise: ' . implode('; ', $result['errors']));
        } else {
            Flash::set('error', implode(' ', $result['errors']));
        }

        $this->app->redirect('/admin/media');
    }

    public function edit(string $id): void
    {
        $mid = (int) $id;
        $media = $this->app->mediaRepository()->findById($mid);
        if ($media === null) {
            Flash::set('error', 'Medium nicht gefunden.');
            $this->app->redirect('/admin/media');
        }

        $categories = $this->app->categoryRepository()->listAllOrdered();
        $this->render(
            'admin/media/edit',
            [
                'title' => 'Medium bearbeiten',
                'media' => $media,
                'categories' => $categories,
                'user' => $this->app->auth()->user(),
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function update(string $id): void
    {
        $mid = (int) $id;
        $media = $this->app->mediaRepository()->findById($mid);
        if ($media === null) {
            Flash::set('error', 'Medium nicht gefunden.');
            $this->app->redirect('/admin/media');
        }

        $title = trim((string) $this->app->request()->post('title', ''));
        $description = (string) $this->app->request()->post('description', '');
        $categoryId = $this->app->mediaAdminService()->resolveCategoryId($this->app->request()->post('category_id'));
        $visible = $this->app->request()->post('is_visible') === '1';

        $this->app->mediaRepository()->updateMetadata(
            $mid,
            $title !== '' ? $title : $media->title,
            $description,
            $categoryId,
            $visible
        );

        Flash::set('success', 'Änderungen gespeichert.');
        $this->app->redirect('/admin/media/' . $mid . '/edit');
    }

    public function destroy(string $id): void
    {
        $mid = (int) $id;
        if (! $this->app->mediaAssetService()->deleteCompletely($mid)) {
            Flash::set('error', 'Medium nicht gefunden.');
            $this->app->redirect('/admin/media');
        }

        Flash::set('success', 'Medium gelöscht.');
        $this->app->redirect('/admin/media');
    }

    public function reorder(): void
    {
        $svc = $this->app->mediaAdminService();
        $period = $svc->normalizePeriod((string) $this->app->request()->post('period', 'all'));
        $result = $svc->reorderFromPost($period, $this->app->request()->post('order'));

        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->app->redirect('/admin/media' . $result['query']);

            return;
        }

        Flash::set('success', 'Reihenfolge gespeichert.');
        $this->app->redirect('/admin/media' . $result['query']);
    }

    public function bulkCategory(): void
    {
        $svc = $this->app->mediaAdminService();
        $periodRaw = (string) $this->app->request()->post('period', 'all');
        $ids = $svc->parseBulkIdsFromPost($this->app->request()->post('ids'));

        $result = $svc->bulkAssignCategoryFromPost(
            $ids,
            $this->app->request()->post('bulk_category_id'),
            $periodRaw
        );

        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->app->redirect('/admin/media' . $result['query']);

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
        $this->app->redirect('/admin/media' . $q);
    }

    public function inlineTitle(): void
    {
        $idRaw = $this->app->request()->post('id');
        $titleRaw = $this->app->request()->post('title');

        if (! is_string($idRaw) || ! ctype_digit($idRaw)) {
            Flash::set('error', 'Ungültige Anfrage.');
            $this->app->redirect('/admin/media');
        }

        $mid = (int) $idRaw;
        $media = $this->app->mediaRepository()->findById($mid);
        if ($media === null) {
            Flash::set('error', 'Medium nicht gefunden.');
            $this->app->redirect('/admin/media');
        }

        $title = is_string($titleRaw) ? trim($titleRaw) : '';
        $this->app->mediaRepository()->updateTitle($mid, $title !== '' ? $title : $media->title);

        Flash::set('success', 'Titel aktualisiert.');
        $this->app->redirect('/admin/media');
    }
}
