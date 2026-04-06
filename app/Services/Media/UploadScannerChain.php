<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Media;

/**
 * Führt alle registrierten {@see UploadContentScannerInterface} nacheinander aus.
 *
 * @phpstan-param list<UploadContentScannerInterface> $scanners
 */
final class UploadScannerChain
{
    /**
     * @param list<UploadContentScannerInterface> $scanners
     */
    public function __construct(
        private array $scanners
    ) {
    }

    public function scan(string $absolutePathOnDisk, string $mimeType): void
    {
        foreach ($this->scanners as $scanner) {
            $scanner->scanFile($absolutePathOnDisk, $mimeType);
        }
    }
}
