<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Events;

/**
 * Nach erfolgreichem Anwenden eines GitHub-ZIP-Updates im Admin.
 */
final readonly class AfterZipUpdateEvent
{
    public function __construct(
        public string $projectRoot,
        public string $previousVersion,
        public string $newVersion,
        public string $releaseTag
    ) {
    }
}
