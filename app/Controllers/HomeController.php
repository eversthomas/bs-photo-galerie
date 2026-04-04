<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers;

/**
 * Öffentliche Startseite (Platzhalter bis Galerie-Frontend).
 */
final class HomeController extends BaseController
{
    public function index(): void
    {
        $title = 'BS Photo Galerie';
        $this->render('home', compact('title'));
    }
}
