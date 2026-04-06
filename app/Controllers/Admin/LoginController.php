<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\CsrfToken;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\LoginRedirect;

/**
 * Admin-Anmeldung.
 */
final class LoginController extends BaseController
{
    public function showForm(): void
    {
        $redirect = LoginRedirect::sanitize($this->app->request()->query('redirect'));
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
        if ($this->app->auth()->check()) {
            $this->app->redirect('/admin');
        }

        $username = trim((string) $this->app->request()->post('username', ''));
        $password = (string) $this->app->request()->post('password', '');

        if ($username === '' || $password === '') {
            Flash::set('error', 'Bitte Benutzername und Passwort eingeben.');
            $this->app->redirect('/admin/login');
        }

        if ($this->app->auth()->attempt($username, $password)) {
            CsrfToken::rotate();
            $next = LoginRedirect::sanitize(trim((string) $this->app->request()->post('redirect', '')));
            if ($next !== null) {
                $this->app->redirect($next);

                return;
            }
            $this->app->redirect('/admin');
        }

        Flash::set('error', 'Zugangsdaten sind ungültig.');
        $this->app->redirect('/admin/login');
    }
}
