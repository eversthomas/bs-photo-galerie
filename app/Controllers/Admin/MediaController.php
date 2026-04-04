<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Services\Media\UploadedFiles;

/**
 * Medienliste, Upload, Bearbeiten, Sortierung, Löschen.
 */
final class MediaController extends BaseController
{
    /** @var array<string, string> */
    private const MEDIA_PERIOD_LABELS = [
        'all' => 'Alle',
        'hour' => 'Letzte Stunde',
        'day' => 'Letzte 7 Tage',
        'week' => 'Letzte 4 Wochen',
        'month' => 'Letzte 12 Monate',
    ];

    public function index(): void
    {
        $period = $this->normalizeMediaPeriod($this->app->request()->query('period', 'all') ?? 'all');
        $items = $this->app->mediaRepository()->listByUploadPeriod($period, 200, 0);
        $categories = $this->app->categoryRepository()->listAllOrdered();
        $this->render(
            'admin/media/index',
            [
                'title' => 'Medien',
                'items' => $items,
                'categories' => $categories,
                'mediaPeriod' => $period,
                'mediaPeriodLabel' => self::MEDIA_PERIOD_LABELS[$period] ?? 'Alle',
                'mediaPeriodLabels' => self::MEDIA_PERIOD_LABELS,
                'user' => $this->app->auth()->user(),
                'flash' => Flash::pull() ?? [],
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
                'flash' => Flash::pull() ?? [],
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

        $categoryId = $this->resolveCategoryId($this->app->request()->post('category_id'));
        $titleBase = $this->app->request()->post('title');
        $titleBase = is_string($titleBase) ? trim($titleBase) : null;
        if ($titleBase === '') {
            $titleBase = null;
        }

        $result = $this->app->mediaUploadService()->processMany($files, $categoryId, $titleBase);

        if ($result['imported'] > 0 && $result['errors'] === []) {
            Flash::set('success', $result['imported'] . ' Datei(en) erfolgreich hochgeladen.');
        } elseif ($result['imported'] > 0) {
            Flash::set(
                'success',
                $result['imported'] . ' Datei(en) hochgeladen. Hinweise: ' . implode('; ', $result['errors'])
            );
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
                'flash' => Flash::pull() ?? [],
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
        $categoryId = $this->resolveCategoryId($this->app->request()->post('category_id'));
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
        $period = $this->normalizeMediaPeriod((string) $this->app->request()->post('period', 'all'));
        $q = $this->mediaPeriodQuery($period);

        $raw = $this->app->request()->post('order');
        if (! is_string($raw) || trim($raw) === '') {
            Flash::set('error', 'Keine Reihenfolge übermittelt.');
            $this->app->redirect('/admin/media' . $q);

            return;
        }

        if ($period !== 'all') {
            Flash::set('error', 'Reihenfolge ist nur in der Ansicht „Alle“ möglich.');
            $this->app->redirect('/admin/media' . $q);

            return;
        }

        $parts = array_map('trim', explode(',', $raw));
        $ids = [];
        foreach ($parts as $p) {
            if ($p !== '' && ctype_digit($p)) {
                $ids[] = (int) $p;
            }
        }

        $items = $this->app->mediaRepository()->listByUploadPeriod('all', 200, 0);
        $expected = array_map(static fn ($m) => $m->id, $items);
        $expectedSorted = $expected;
        sort($expectedSorted);
        $gotSorted = $ids;
        sort($gotSorted);

        if ($expectedSorted !== $gotSorted || count($ids) !== count($expected)) {
            Flash::set('error', 'Sortierung ungültig (Kontext hat sich geändert). Bitte Seite neu laden.');
            $this->app->redirect('/admin/media' . $q);

            return;
        }

        $this->app->mediaRepository()->reorderByOrderedIds($ids);
        Flash::set('success', 'Reihenfolge gespeichert.');
        $this->app->redirect('/admin/media' . $q);
    }

    public function bulkCategory(): void
    {
        $rawIds = $this->app->request()->post('ids');
        $ids = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $v) {
                if (is_string($v) && ctype_digit($v)) {
                    $ids[] = (int) $v;
                } elseif (is_int($v) && $v > 0) {
                    $ids[] = $v;
                }
            }
        }

        if ($ids === []) {
            Flash::set('error', 'Bitte mindestens ein Bild auswählen.');
            $this->app->redirect('/admin/media' . $this->mediaPeriodQuery(
                $this->normalizeMediaPeriod((string) $this->app->request()->post('period', 'all'))
            ));

            return;
        }

        $ids = array_slice(array_values(array_unique($ids)), 0, 200);

        $categoryId = $this->resolveCategoryId($this->app->request()->post('bulk_category_id'));
        $updated = $this->app->mediaRepository()->bulkAssignCategory($ids, $categoryId);
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
        $this->app->redirect('/admin/media' . $this->mediaPeriodQuery(
            $this->normalizeMediaPeriod((string) $this->app->request()->post('period', 'all'))
        ));
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

    private function resolveCategoryId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_string($raw) && ! is_int($raw)) {
            return null;
        }
        $s = (string) $raw;
        if (! ctype_digit($s)) {
            return null;
        }
        $id = (int) $s;
        foreach ($this->app->categoryRepository()->listAllOrdered() as $row) {
            if ($row['id'] === $id) {
                return $id;
            }
        }

        return null;
    }

    private function normalizeMediaPeriod(string $raw): string
    {
        return isset(self::MEDIA_PERIOD_LABELS[$raw]) ? $raw : 'all';
    }

    private function mediaPeriodQuery(string $period): string
    {
        return $period === 'all' ? '' : ('?period=' . rawurlencode($period));
    }
}
