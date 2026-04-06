<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;

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
                'flash' => Flash::pull(),
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
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function store(): void
    {
        $name = (string) $this->app->request()->post('name', '');
        $slugInput = (string) $this->app->request()->post('slug', '');
        $isPublic = $this->app->request()->post('is_public', '') === '1';

        $result = $this->app->categoryAdminService()->create($name, $slugInput, $isPublic);
        if (! $result['ok']) {
            Flash::set('error', $result['error']);
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
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function update(string $id): void
    {
        $cid = (int) $id;
        $name = (string) $this->app->request()->post('name', '');
        $slugInput = (string) $this->app->request()->post('slug', '');
        $isPublic = $this->app->request()->post('is_public', '') === '1';

        $result = $this->app->categoryAdminService()->update($cid, $name, $slugInput, $isPublic);
        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->app->redirect('/admin/categories/' . $cid . '/edit');
        }

        Flash::set('success', 'Kategorie gespeichert.');
        $this->app->redirect('/admin/categories');
    }

    public function delete(string $id): void
    {
        $cid = (int) $id;
        $result = $this->app->categoryAdminService()->delete($cid);
        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->app->redirect('/admin/categories');
        }

        Flash::set('success', 'Kategorie gelöscht. Verknüpfte Bilder haben keine Kategorie mehr (Datenbank).');
        $this->app->redirect('/admin/categories');
    }
}
