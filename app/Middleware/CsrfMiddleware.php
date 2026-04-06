<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Middleware;

use BSPhotoGalerie\Core\CsrfToken;
use BSPhotoGalerie\Core\HttpContext;

/**
 * Prüft das CSRF-Token bei POST-Anfragen.
 */
final class CsrfMiddleware
{
    public function __construct(
        private HttpContext $http
    ) {
    }

    public function validatePost(): void
    {
        $token = $this->http->request()->post('_csrf');
        if (! CsrfToken::validate(is_string($token) ? $token : null)) {
            $this->http->abort(403, 'Ungültige oder fehlende Sitzung (CSRF). Bitte Formular erneut laden.');
        }
    }
}
