<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\CsrfToken;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Core\LoginRedirect;
use BSPhotoGalerie\Services\AuthService;

/**
 * Admin-Anmeldung.
 */
final class LoginController extends BaseController
{
    public function __construct(
        HttpContext $http,
        private AuthService $auth
    ) {
        parent::__construct($http);
    }

    public function showForm(): void
    {
        $redirect = LoginRedirect::sanitize($this->http->request()->query('redirect'));
        $this->render(
            'admin/login',
            [
                'flash' => Flash::pull(),
                'title' => 'Anmeldung',
                'redirectAfterLogin' => $redirect,
            ],
            'admin/layout'
        );
    }

    public function login(): void
    {
        if ($this->auth->check()) {
            $this->http->redirect('/admin');
        }

        $username = trim((string) $this->http->request()->post('username', ''));
        $password = (string) $this->http->request()->post('password', '');

        if ($username === '' || $password === '') {
            Flash::set('error', 'Bitte Benutzername und Passwort eingeben.');
            $this->http->redirect('/admin/login');
        }

        if ($this->auth->attempt($username, $password)) {
            CsrfToken::rotate();
            $next = LoginRedirect::sanitize(trim((string) $this->http->request()->post('redirect', '')));
            if ($next !== null) {
                $this->http->redirect($next);

                return;
            }
            $this->http->redirect('/admin');
        }

        Flash::set('error', 'Zugangsdaten sind ungültig.');
        $this->http->redirect('/admin/login');
    }
}
