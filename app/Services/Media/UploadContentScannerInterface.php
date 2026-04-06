<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Media;

/**
 * Optionaler Hook nach Speichern der Datei auf der Platte, vor Metadaten/DB (z. B. externes AV).
 */
interface UploadContentScannerInterface
{
    /**
     * @throws \RuntimeException wenn die Datei aus Sicherheitsgründen abgelehnt wird
     */
    public function scanFile(string $absolutePathOnDisk, string $mimeType): void;
}
