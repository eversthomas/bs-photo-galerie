<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Import;

use BSPhotoGalerie\Config\ImportSettings;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Services\Media\MediaUploadService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Scannt den Import-Ordner, übernimmt neue Bilder in die Galerie, optional FTP-Vorab-Download und DB-Bereinigung.
 */
final class MediaImportService
{
    public function __construct(
        private string $projectRoot,
        private MediaRepository $mediaRepository,
        private MediaUploadService $mediaUploadService,
        private ImportSettings $importSettings
    ) {
    }

    /**
     * @return array{
     *   imported:int,
     *   removed_db:int,
     *   ftp_downloaded:int,
     *   ftp_skipped:int,
     *   errors:list<string>
     * }
     */
    public function run(?int $categoryId, bool $pullFtp, bool $removeMissingFiles): array
    {
        $errors = [];
        $ftpDownloaded = 0;
        $ftpSkipped = 0;

        $importRoot = $this->importRootAbsolute();
        if (! is_dir($importRoot)) {
            if (! mkdir($importRoot, 0755, true) && ! is_dir($importRoot)) {
                return [
                    'imported' => 0,
                    'removed_db' => 0,
                    'ftp_downloaded' => 0,
                    'ftp_skipped' => 0,
                    'errors' => ['Import-Ordner existiert nicht und konnte nicht angelegt werden: ' . $importRoot],
                ];
            }
        }

        if ($pullFtp && $this->importSettings->ftpEnabled()) {
            $ftpCfg = $this->importSettings->ftpCredentials();
            if ($ftpCfg === null) {
                $errors[] = 'FTP ist aktiviert, aber nicht vollständig konfiguriert.';
            } else {
                $pull = (new FtpPullService())->pullFlatDirectory($importRoot, $ftpCfg);
                $ftpDownloaded = $pull['downloaded'];
                $ftpSkipped = $pull['skipped'];
                $errors = array_merge($errors, $pull['errors']);
            }
        }

        $imported = 0;
        $files = $this->scanImageFiles($importRoot);
        $deleteSource = $this->importSettings->deleteSourceAfterSuccess();

        foreach ($files as $path) {
            try {
                $this->assertPathInsideDirectory($path, $importRoot);
                $this->mediaUploadService->importFromFilesystemPath(
                    $path,
                    $categoryId,
                    null,
                    $deleteSource
                );
                ++$imported;
            } catch (RuntimeException $e) {
                $errors[] = basename($path) . ': ' . $e->getMessage();
            }
        }

        $removed = 0;
        if ($removeMissingFiles) {
            $removed = $this->removeOrphanDbRows();
        }

        return [
            'imported' => $imported,
            'removed_db' => $removed,
            'ftp_downloaded' => $ftpDownloaded,
            'ftp_skipped' => $ftpSkipped,
            'errors' => $errors,
        ];
    }

    public function importRootAbsolute(): string
    {
        $rel = $this->importSettings->localRelativePath();

        return $this->projectRoot . '/' . $rel;
    }

    /**
     * @return list<string>
     */
    private function scanImageFiles(string $importRoot): array
    {
        $resolved = realpath($importRoot);
        if ($resolved === false || ! is_dir($resolved)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolved, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $paths = [];
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile() || ! $file->isReadable()) {
                continue;
            }
            $path = $file->getPathname();
            if (str_contains($path, DIRECTORY_SEPARATOR . '.')) {
                continue;
            }
            if (! preg_match('/\.(jpe?g|png|webp|gif)$/i', $file->getFilename())) {
                continue;
            }
            $paths[] = $path;
        }

        sort($paths);

        return $paths;
    }

    private function assertPathInsideDirectory(string $filePath, string $directoryPath): void
    {
        $d = realpath($directoryPath);
        $f = realpath($filePath);
        if ($d === false || $f === false || ! is_file($f)) {
            throw new RuntimeException('Ungültiger Import-Pfad.');
        }

        $prefix = rtrim($d, '/') . '/';
        if (! str_starts_with($f, $prefix) && $f !== $d) {
            throw new RuntimeException('Pfad liegt außerhalb des Import-Ordners.');
        }
    }

    private function removeOrphanDbRows(): int
    {
        $removed = 0;
        $publicRoot = realpath($this->projectRoot . '/public');
        if ($publicRoot === false) {
            return 0;
        }

        foreach ($this->mediaRepository->listAllStoragePaths() as $row) {
            $id = $row['id'];
            $rel = trim(str_replace('\\', '/', $row['storage_path']), '/');
            if ($rel === '' || str_contains($rel, '..')) {
                continue;
            }

            $full = $this->projectRoot . '/public/' . $rel;
            $real = realpath($full);
            $exists = $real !== false && is_file($real)
                && str_starts_with($real, rtrim($publicRoot, '/') . DIRECTORY_SEPARATOR);

            if (! $exists) {
                $thumb = $this->projectRoot . '/storage/thumbnails/' . $id . '.jpg';
                @unlink($thumb);
                $this->mediaRepository->deleteById($id);
                ++$removed;
            }
        }

        return $removed;
    }
}
