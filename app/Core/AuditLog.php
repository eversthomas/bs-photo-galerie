<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

/**
 * Dienschaftssicht auf {@see AuditLogger} (pro Projektroot).
 */
final class AuditLog
{
    public function __construct(
        private string $projectRoot
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function record(string $action, array $context = []): void
    {
        AuditLogger::append($this->projectRoot, $action, $context);
    }
}
