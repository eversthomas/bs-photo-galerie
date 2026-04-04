<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\SlugGenerator;

/**
 * Kategorien verwalten.
 */
final class CategoryController extends BaseController
{
    public function index(): void
    {
        $items = $this->app->categoryRepository()->listAllOrdered();
        $this->render(
            'admin/categories/index',
            [
                'title' => 'Kategorien',
                'items' => $items,
                'user' => $this->app->auth()->user(),
                'flash' => Flash::pull() ?? [],
            ],
            'admin/layout'
        );
    }

    public function create(): void
    {
        $this->render(
            'admin/categories/form',
            [
                'title' => 'Kategorie anlegen',
                'category' => null,
                'user' => $this->app->auth()->user(),
                'flash' => Flash::pull() ?? [],
            ],
            'admin/layout'
        );
    }

    public function store(): void
    {
        $name = trim((string) $this->app->request()->post('name', ''));
        if ($name === '') {
            Flash::set('error', 'Bitte einen Namen eingeben.');
            $this->app->redirect('/admin/categories/create');
        }

        $slugInput = trim((string) $this->app->request()->post('slug', ''));
        $base = $slugInput !== '' ? $slugInput : $name;
        $slug = $this->uniqueSlug(SlugGenerator::slugify($base), null);

        $id = $this->app->categoryRepository()->insert(mb_substr($name, 0, 255), $slug);
        if ($id < 1) {
            Flash::set('error', 'Speichern fehlgeschlagen.');
            $this->app->redirect('/admin/categories/create');
        }

        Flash::set('success', 'Kategorie angelegt.');
        $this->app->redirect('/admin/categories');
    }

    public function edit(string $id): void
    {
        $cid = (int) $id;
        $cat = $this->app->categoryRepository()->findById($cid);
        if ($cat === null) {
            Flash::set('error', 'Kategorie nicht gefunden.');
            $this->app->redirect('/admin/categories');
        }

        $this->render(
            'admin/categories/form',
            [
                'title' => 'Kategorie bearbeiten',
                'category' => $cat,
                'user' => $this->app->auth()->user(),
                'flash' => Flash::pull() ?? [],
            ],
            'admin/layout'
        );
    }

    public function update(string $id): void
    {
        $cid = (int) $id;
        $cat = $this->app->categoryRepository()->findById($cid);
        if ($cat === null) {
            Flash::set('error', 'Kategorie nicht gefunden.');
            $this->app->redirect('/admin/categories');
        }

        $name = trim((string) $this->app->request()->post('name', ''));
        if ($name === '') {
            Flash::set('error', 'Bitte einen Namen eingeben.');
            $this->app->redirect('/admin/categories/' . $cid . '/edit');
        }

        $slugInput = trim((string) $this->app->request()->post('slug', ''));
        $base = $slugInput !== '' ? $slugInput : $name;
        $slug = $this->uniqueSlug(SlugGenerator::slugify($base), $cid);

        $this->app->categoryRepository()->update($cid, mb_substr($name, 0, 255), $slug);
        Flash::set('success', 'Kategorie gespeichert.');
        $this->app->redirect('/admin/categories');
    }

    public function delete(string $id): void
    {
        $cid = (int) $id;
        if ($this->app->categoryRepository()->findById($cid) === null) {
            Flash::set('error', 'Kategorie nicht gefunden.');
            $this->app->redirect('/admin/categories');
        }

        $this->app->categoryRepository()->delete($cid);
        Flash::set('success', 'Kategorie gelöscht. Verknüpfte Bilder haben keine Kategorie mehr (Datenbank).');
        $this->app->redirect('/admin/categories');
    }

    private function uniqueSlug(string $slug, ?int $exceptId): string
    {
        $candidate = $slug;
        $n = 2;
        while ($this->app->categoryRepository()->slugExists($candidate, $exceptId)) {
            $candidate = $slug . '-' . $n;
            ++$n;
        }

        return mb_substr($candidate, 0, 255);
    }
}
