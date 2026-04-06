<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Application;

use BSPhotoGalerie\Config\ImportSettings;
use BSPhotoGalerie\Core\Request;
use BSPhotoGalerie\Services\Domain\MediaAdminService;
use BSPhotoGalerie\Services\Import\MediaImportService;

/**
 * Anwendungsfall: Ordner-/FTP-Import aus dem Admin starten und Meldung erzeugen.
 */
final class ImportRunService
{
    public function __construct(
        private MediaImportService $mediaImport,
        private ImportSettings $importSettings,
        private MediaAdminService $mediaAdmin
    ) {
    }

    /**
     * @return array{type: 'success'|'error', message: string}
     */
    public function runFromRequest(Request $request): array
    {
        $pullFtp = $request->post('pull_ftp') === '1';
        $removeMissing = $request->post('remove_missing') === '1';

        $categoryId = $this->mediaAdmin->resolveCategoryId($request->post('category_id'));

        $result = $this->mediaImport->run($categoryId, $pullFtp, $removeMissing);

        $parts = [];
        if ($pullFtp && $this->importSettings->ftpEnabled()) {
            $parts[] = 'FTP: ' . $result['ftp_downloaded'] . ' heruntergeladen, ' . $result['ftp_skipped'] . ' übersprungen.';
        }
        $parts[] = 'Importiert: ' . $result['imported'] . ' Datei(en).';
        if ($removeMissing) {
            $parts[] = 'Aus Datenbank entfernt (fehlende Datei): ' . $result['removed_db'] . '.';
        }

        $message = implode(' ', $parts);
        if ($result['errors'] !== []) {
            return [
                'type' => 'error',
                'message' => $message . ' — ' . implode('; ', $result['errors']),
            ];
        }

        return ['type' => 'success', 'message' => $message];
    }
}
