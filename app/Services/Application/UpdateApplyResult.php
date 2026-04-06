<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Application;

/**
 * Rückgabe der Update-Anwend-Logik für Flash + Redirect (ohne Session in der Service-Schicht).
 */
final readonly class UpdateApplyResult
{
    public function __construct(
        public string $flashType,
        public string $flashMessage
    ) {
    }
}
