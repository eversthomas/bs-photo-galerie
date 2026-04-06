<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Middleware;

use BSPhotoGalerie\Core\Flash;
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
        if ($this->auth->check() && $this->auth->idleExpired()) {
            $this->auth->logout();
            Flash::set('info', 'Aus Sicherheitsgründen wurde Ihre Sitzung nach längerer Inaktivität beendet. Bitte melden Sie sich erneut an.');
            $this->http->redirect('/admin/login');
        }

        if (! $this->auth->check()) {
            $this->http->redirect('/admin/login');
        }

        $this->auth->touchActivity();
    }
}
