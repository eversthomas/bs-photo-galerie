<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Config\ImportSettings;
use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Services\Application\ImportRunService;
use BSPhotoGalerie\Services\AuthService;
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
        private ImportRunService $importRun
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
        $outcome = $this->importRun->runFromRequest($this->http->request());
        Flash::set($outcome['type'], $outcome['message']);
        $this->http->redirect('/admin/import');
    }
}
