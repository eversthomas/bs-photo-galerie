<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\CsrfToken;
use BSPhotoGalerie\Core\Flash;

/**
 * Geschützter Admin-Bereich (Dashboard-Stub).
 */
final class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = $this->app->auth()->user();
        $title = 'Dashboard';
        $this->render(
            'admin/dashboard',
            [
                'title' => $title,
                'user' => $user,
                'flash' => Flash::pull() ?? [],
            ],
            'admin/layout'
        );
    }

    public function logout(): void
    {
        $this->app->auth()->logout();
        CsrfToken::rotate();
        Flash::set('success', 'Sie wurden abgemeldet.');
        $this->app->redirect('/admin/login');
    }
}
