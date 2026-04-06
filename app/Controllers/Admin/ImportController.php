<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Config\ImportSettings;
use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Services\AuthService;
use BSPhotoGalerie\Services\Domain\MediaAdminService;
use BSPhotoGalerie\Services\Import\MediaImportService;

/**
 * Ordner- und optional FTP-Import in die Galerie.
 */
final class ImportController extends BaseController
{
    public function __construct(
        HttpContext $http,
        private MediaImportService $mediaImport,
        private CategoryRepository $categoryRepository,
        private ImportSettings $importSettings,
        private AuthService $auth,
        private MediaAdminService $mediaAdmin
    ) {
        parent::__construct($http);
    }

    public function index(): void
    {
        $importPath = $this->mediaImport->importRootAbsolute();
        $categories = $this->categoryRepository->listAllOrdered();
        $ftp = $this->importSettings->ftpEnabled();

        $this->render(
            'admin/import/index',
            [
                'title' => 'Import',
                'importPath' => $importPath,
                'importPathRelative' => $this->importSettings->localRelativePath(),
                'ftpConfigured' => $ftp,
                'categories' => $categories,
                'user' => $this->auth->user(),
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function run(): void
    {
        $pullFtp = isset($_POST['pull_ftp']) && $_POST['pull_ftp'] === '1';
        $removeMissing = isset($_POST['remove_missing']) && $_POST['remove_missing'] === '1';

        $categoryId = $this->mediaAdmin->resolveCategoryId($this->http->request()->post('category_id'));

        $result = $this->mediaImport->run($categoryId, $pullFtp, $removeMissing);

        $parts = [];
        if ($pullFtp && $this->importSettings->ftpEnabled()) {
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

        $this->http->redirect('/admin/import');
    }
}
