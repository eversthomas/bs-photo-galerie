<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Middleware;

use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Services\AuthService;

/**
 * Prüft, ob ein Benutzer für geschützte Admin-Routen angemeldet ist.
 */
final class AuthMiddleware
{
    public function __construct(
        private AuthService $auth,
        private HttpContext $http
    ) {
    }

    public function requireUser(): void
    {
        if (! $this->auth->check()) {
            $this->http->redirect('/admin/login');
        }
    }
}
