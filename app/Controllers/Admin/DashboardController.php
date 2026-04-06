<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\CsrfToken;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Services\AuthService;

/**
 * Geschützter Admin-Bereich (Dashboard-Stub).
 */
final class DashboardController extends BaseController
{
    public function __construct(
        HttpContext $http,
        private AuthService $auth
    ) {
        parent::__construct($http);
    }

    public function index(): void
    {
        $user = $this->auth->user();
        $title = 'Dashboard';
        $this->render(
            'admin/dashboard',
            [
                'title' => $title,
                'user' => $user,
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function logout(): void
    {
        $this->auth->logout();
        CsrfToken::rotate();
        Flash::set('success', 'Sie wurden abgemeldet.');
        $this->http->redirect('/admin/login');
    }
}
