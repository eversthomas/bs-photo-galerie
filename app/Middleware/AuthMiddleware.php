<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Middleware;

use BSPhotoGalerie\Core\Application;

/**
 * Prüft, ob ein Benutzer für geschützte Admin-Routen angemeldet ist.
 */
final class AuthMiddleware
{
    public function __construct(
        private Application $app
    ) {
    }

    public function requireUser(): void
    {
        if (! $this->app->auth()->check()) {
            $this->app->redirect('/admin/login');
        }
    }
}
