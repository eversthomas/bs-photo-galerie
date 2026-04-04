<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Middleware;

use BSPhotoGalerie\Core\Application;
use BSPhotoGalerie\Core\CsrfToken;

/**
 * Prüft das CSRF-Token bei POST-Anfragen.
 */
final class CsrfMiddleware
{
    public function __construct(
        private Application $app
    ) {
    }

    public function validatePost(): void
    {
        $token = $this->app->request()->post('_csrf');
        if (! CsrfToken::validate(is_string($token) ? $token : null)) {
            $this->app->abort(403, 'Ungültige oder fehlende Sitzung (CSRF). Bitte Formular erneut laden.');
        }
    }
}
