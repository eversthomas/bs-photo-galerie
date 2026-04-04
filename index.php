<?php

declare(strict_types=1);

/**
 * Fallback, wenn DirectoryIndex diese Datei aus dem Projektroot bedient.
 * Korrekt: DocumentRoot = …/bs-photo-galerie/public
 */
require __DIR__ . '/public/index.php';
