<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Media;

use BSPhotoGalerie\Events\EventDispatcher;
use BSPhotoGalerie\Events\MediaUploadedEvent;
use BSPhotoGalerie\Models\MediaRepository;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Validierung, Speicherung, Hashing, EXIF, Thumbnail und DB-Eintrag für Bild-Uploads und Ordner-Import.
 */
final class MediaUploadService
{
    public function __construct(
        private string $projectRoot,
        private MediaRepository $mediaRepository,
        private UploadSecurityPolicy $uploadPolicy,
        private ImageManager $imageManager,
        private EventDispatcher $events,
        private UploadScannerChain $uploadScanners
    ) {
    }

    /**
     * Importiert eine bereits auf dem Server liegende Datei (Kopie nach uploads/, dann wie Upload).
     *
     * @param bool $deleteSourceAfterSuccess Originaldatei nach Erfolg löschen (z. B. Import-Ordner leeren)
     */
    public function importFromFilesystemPath(
        string $absoluteSourcePath,
        ?int $categoryId,
        ?string $titleBase,
        bool $deleteSourceAfterSuccess = true
    ): void {
        $resolved = realpath($absoluteSourcePath);
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new RuntimeException('Quelldatei nicht lesbar: ' . $absoluteSourcePath);
        }

        $max = $this->uploadPolicy->maxUploadBytes();
        $size = filesize($resolved);
        if ($size === false || $size > $max) {
            throw new RuntimeException(
                'Datei zu groß (max. ' . (string) (int) round($max / 1024 / 1024) . ' MB).'
            );
        }

        $mime = $this->detectMime($resolved);
        $allowed = $this->uploadPolicy->allowedMimeTypes();
        if (! in_array($mime, $allowed, true)) {
            throw new RuntimeException('Dateityp nicht erlaubt (' . $mime . ').');
        }

        $ext = $this->extensionForMime($mime);
        if ($ext === null) {
            throw new RuntimeException('Unbekannter Dateityp.');
        }

        $hash = hash_file('sha256', $resolved);
        if ($hash === false || strlen($hash) !== 64) {
            throw new RuntimeException('Hash konnte nicht berechnet werden.');
        }

        if ($this->mediaRepository->findIdByHash($hash) !== null) {
            throw new RuntimeException('Diese Datei existiert bereits (gleicher Hash).');
        }

        $relativeDir = 'uploads/' . date('Y/m');
        $publicDir = $this->projectRoot . '/public/' . $relativeDir;
        if (! is_dir($publicDir) && ! mkdir($publicDir, 0755, true) && ! is_dir($publicDir)) {
            throw new RuntimeException('Zielordner konnte nicht angelegt werden.');
        }

        $basename = bin2hex(random_bytes(16)) . '.' . $ext;
        $relativePath = $relativeDir . '/' . $basename;
        $absolutePath = $this->projectRoot . '/public/' . $relativePath;

        if (! copy($resolved, $absolutePath)) {
            throw new RuntimeException('Datei konnte nicht nach uploads/ kopiert werden.');
        }

        $this->uploadScanners->scan($absolutePath, $mime);

        try {
            $this->persistNewMediaAtPath(
                $absolutePath,
                $relativePath,
                $basename,
                $mime,
                $hash,
                basename($resolved),
                $categoryId,
                $titleBase
            );
        } catch (RuntimeException $e) {
            @unlink($absolutePath);
            throw $e;
        } catch (\Throwable $e) {
            @unlink($absolutePath);
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        if ($deleteSourceAfterSuccess && is_file($resolved)) {
            @unlink($resolved);
        }
    }

    /**
     * @param list<array{name:string,tmp_name:string,error:int,size:int,type:string}> $files
     *
     * @return array{imported:int, errors:list<string>}
     */
    public function processMany(array $files, ?int $categoryId, ?string $titleBase): array
    {
        $imported = 0;
        $errors = [];

        $multi = count($files) > 1;
        foreach ($files as $index => $file) {
            try {
                $titleForFile = null;
                if ($titleBase !== null && $titleBase !== '') {
                    $titleForFile = $multi ? $titleBase . ' (' . ($index + 1) . ')' : $titleBase;
                }
                $this->processOne($file, $categoryId, $titleForFile);
                ++$imported;
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @param array{name:string,tmp_name:string,error:int,size:int,type:string} $file
     */
    private function processOne(array $file, ?int $categoryId, ?string $titleBase): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload fehlgeschlagen (Fehlercode ' . (int) ($file['error'] ?? 0) . ').');
        }

        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || ! is_uploaded_file($tmp)) {
            throw new RuntimeException('Ungültige Upload-Datei.');
        }

        $max = $this->uploadPolicy->maxUploadBytes();
        if (($file['size'] ?? 0) > $max) {
            throw new RuntimeException(
                'Datei zu groß (max. ' . (string) (int) round($max / 1024 / 1024) . ' MB).'
            );
        }

        $mime = $this->detectMime($tmp);
        $allowed = $this->uploadPolicy->allowedMimeTypes();
        if (! in_array($mime, $allowed, true)) {
            throw new RuntimeException('Dateityp nicht erlaubt (' . $mime . ').');
        }

        $ext = $this->extensionForMime($mime);
        if ($ext === null) {
            throw new RuntimeException('Unbekannter Dateityp.');
        }

        $hash = hash_file('sha256', $tmp);
        if ($hash === false || strlen($hash) !== 64) {
            throw new RuntimeException('Hash konnte nicht berechnet werden.');
        }

        if ($this->mediaRepository->findIdByHash($hash) !== null) {
            throw new RuntimeException('Diese Datei existiert bereits (gleicher Hash).');
        }

        $relativeDir = 'uploads/' . date('Y/m');
        $publicDir = $this->projectRoot . '/public/' . $relativeDir;
        if (! is_dir($publicDir) && ! mkdir($publicDir, 0755, true) && ! is_dir($publicDir)) {
            throw new RuntimeException('Zielordner konnte nicht angelegt werden.');
        }

        $basename = bin2hex(random_bytes(16)) . '.' . $ext;
        $relativePath = $relativeDir . '/' . $basename;
        $absolutePath = $this->projectRoot . '/public/' . $relativePath;

        if (! move_uploaded_file($tmp, $absolutePath)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        $this->uploadScanners->scan($absolutePath, $mime);

        try {
            $this->persistNewMediaAtPath(
                $absolutePath,
                $relativePath,
                $basename,
                $mime,
                $hash,
                $file['name'] ?? '',
                $categoryId,
                $titleBase
            );
        } catch (RuntimeException $e) {
            @unlink($absolutePath);
            throw $e;
        }
    }

    private function persistNewMediaAtPath(
        string $absolutePath,
        string $relativePath,
        string $basename,
        string $mime,
        string $hash,
        string $originalNameForTitle,
        ?int $categoryId,
        ?string $titleBase
    ): void {
        $this->assertMimeOnDiskMatches($absolutePath, $mime);

        try {
            $image = $this->imageManager->read($absolutePath);
            $size = $image->size();
            $width = $size->width();
            $height = $size->height();
        } catch (\Throwable) {
            throw new RuntimeException('Bild konnte nicht gelesen werden (beschädigt oder nicht unterstützt).');
        }

        $maxPx = $this->uploadPolicy->maxImagePixels();
        if ($width < 1 || $height < 1 || $width > 32_000 || $height > 32_000) {
            throw new RuntimeException('Ungültige Bildabmessungen.');
        }
        if ($width * $height > $maxPx) {
            throw new RuntimeException(
                'Bildauflösung zu groß (max. ca. ' . (string) (int) round($maxPx / 1_000_000) . ' Megapixel).'
            );
        }

        $exif = (new ExifExtractor())->extractAsJson($absolutePath, $mime);

        $title = $this->deriveTitle($originalNameForTitle, $titleBase);

        $sortOrder = $this->mediaRepository->nextSortOrder();

        $mediaId = 0;
        try {
            $mediaId = $this->mediaRepository->insert([
                'category_id' => $categoryId,
                'filename' => $basename,
                'storage_path' => $relativePath,
                'file_hash' => $hash,
                'mime_type' => $mime,
                'width' => $width,
                'height' => $height,
                'title' => $title,
                'description' => '',
                'exif_json' => $exif,
                'sort_order' => $sortOrder,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Datenbankfehler beim Speichern.', 0, $e);
        }

        $thumbPath = $this->projectRoot . '/storage/thumbnails/' . $mediaId . '.jpg';
        try {
            (new ThumbnailGenerator($this->imageManager, $this->uploadPolicy->thumbnailMaxWidth()))
                ->createJpegThumbnail($absolutePath, $thumbPath);
        } catch (\Throwable $e) {
            $this->mediaRepository->deleteById($mediaId);
            @unlink($absolutePath);
            @unlink($thumbPath);
            throw new RuntimeException('Vorschaubild konnte nicht erzeugt werden.', 0, $e);
        }

        $this->events->dispatch(
            new MediaUploadedEvent($mediaId, $relativePath, $categoryId)
        );
    }

    /**
     * Nach dem Speichern erneut prüfen (Abweichung Temp ↔ Datei = Ablehnung).
     */
    private function assertMimeOnDiskMatches(string $absolutePath, string $expectedMime): void
    {
        $onDisk = $this->detectMime($absolutePath);
        $expectedMime = strtolower(trim($expectedMime));
        if ($onDisk !== $expectedMime) {
            throw new RuntimeException('Dateiinhalt passt nicht zum erkannten Bildtyp (MIME: ' . $onDisk . ').');
        }
        if (! in_array($onDisk, $this->uploadPolicy->allowedMimeTypes(), true)) {
            throw new RuntimeException('Dateityp nach Speichern nicht erlaubt (' . $onDisk . ').');
        }
    }

    private function detectMime(string $path): string
    {
        if (! function_exists('finfo_open')) {
            throw new RuntimeException('PHP fileinfo ist erforderlich.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('MIME-Erkennung nicht verfügbar.');
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) ? strtolower(trim($mime)) : 'application/octet-stream';
    }

    private function extensionForMime(string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };
    }

    private function deriveTitle(string $originalName, ?string $titleBase): string
    {
        $base = $titleBase !== null ? trim($titleBase) : '';
        if ($base !== '') {
            return mb_substr($base, 0, 255);
        }

        $name = basename($originalName);
        $name = preg_replace('/\.[^.]+$/', '', $name) ?? $name;

        return mb_substr($name, 0, 255);
    }
}
