<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Events;

/**
 * Nach erfolgreichem Git-basierten Software-Update im Admin (Working Copy mit .git).
 */
final readonly class AfterGitUpdateAppliedEvent
{
    public function __construct(
        public string $projectRoot,
        public string $previousVersion,
        public string $newVersion,
        public string $releaseTag
    ) {
    }
}
