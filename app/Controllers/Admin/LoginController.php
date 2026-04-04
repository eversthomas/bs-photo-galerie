<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\CsrfToken;
use BSPhotoGalerie\Core\Flash;

/**
 * Admin-Anmeldung.
 */
final class LoginController extends BaseController
{
    public function showForm(): void
    {
        $this->render('admin/login', ['flash' => Flash::pull() ?? [], 'title' => 'Anmeldung'], 'admin/layout');
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
            $this->app->redirect('/admin');
        }

        Flash::set('error', 'Zugangsdaten sind ungültig.');
        $this->app->redirect('/admin/login');
    }
}
