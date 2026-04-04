<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;

/**
 * Ordner- und optional FTP-Import in die Galerie.
 */
final class ImportController extends BaseController
{
    public function index(): void
    {
        $importPath = $this->app->mediaImportService()->importRootAbsolute();
        $categories = $this->app->categoryRepository()->listAllOrdered();
        $ftp = $this->app->importSettings()->ftpEnabled();

        $this->render(
            'admin/import/index',
            [
                'title' => 'Import',
                'importPath' => $importPath,
                'importPathRelative' => $this->app->importSettings()->localRelativePath(),
                'ftpConfigured' => $ftp,
                'categories' => $categories,
                'user' => $this->app->auth()->user(),
                'flash' => Flash::pull() ?? [],
            ],
            'admin/layout'
        );
    }

    public function run(): void
    {
        $pullFtp = isset($_POST['pull_ftp']) && $_POST['pull_ftp'] === '1';
        $removeMissing = isset($_POST['remove_missing']) && $_POST['remove_missing'] === '1';

        $categoryId = $this->resolveCategoryId($this->app->request()->post('category_id'));

        $result = $this->app->mediaImportService()->run($categoryId, $pullFtp, $removeMissing);

        $parts = [];
        if ($pullFtp && $this->app->importSettings()->ftpEnabled()) {
            $parts[] = 'FTP: ' . $result['ftp_downloaded'] . ' heruntergeladen, ' . $result['ftp_skipped'] . ' übersprungen.';
        }
        $parts[] = 'Importiert: ' . $result['imported'] . ' Datei(en).';
        if ($removeMissing) {
            $parts[] = 'Aus Datenbank entfernt (fehlende Datei): ' . $result['removed_db'] . '.';
        }
        if ($result['errors'] !== []) {
            Flash::set('error', implode(' ', $parts) . ' — ' . implode('; ', $result['errors']));
        } else {
            Flash::set('success', implode(' ', $parts));
        }

        $this->app->redirect('/admin/import');
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
}
