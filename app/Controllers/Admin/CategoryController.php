<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Services\AuthService;
use BSPhotoGalerie\Services\Category\CategoryService;

/**
 * Kategorien verwalten.
 */
final class CategoryController extends BaseController
{
    public function __construct(
        HttpContext $http,
        private CategoryRepository $categoryRepository,
        private CategoryService $categories,
        private AuthService $auth
    ) {
        parent::__construct($http);
    }

    public function index(): void
    {
        $items = $this->categoryRepository->listAllOrdered();
        $this->render(
            'admin/categories/index',
            [
                'title' => 'Kategorien',
                'items' => $items,
                'user' => $this->auth->user(),
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
                'user' => $this->auth->user(),
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function store(): void
    {
        $result = $this->categories->createFromRequest($this->http->request());
        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->http->redirect('/admin/categories/create');
        }

        Flash::set('success', 'Kategorie angelegt.');
        $this->http->redirect('/admin/categories');
    }

    public function edit(string $id): void
    {
        $cid = (int) $id;
        $cat = $this->categoryRepository->findById($cid);
        if ($cat === null) {
            Flash::set('error', 'Kategorie nicht gefunden.');
            $this->http->redirect('/admin/categories');
        }

        $this->render(
            'admin/categories/form',
            [
                'title' => 'Kategorie bearbeiten',
                'category' => $cat,
                'user' => $this->auth->user(),
                'flash' => Flash::pull(),
            ],
            'admin/layout'
        );
    }

    public function update(string $id): void
    {
        $cid = (int) $id;
        $result = $this->categories->updateFromRequest($cid, $this->http->request());
        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->http->redirect('/admin/categories/' . $cid . '/edit');
        }

        Flash::set('success', 'Kategorie gespeichert.');
        $this->http->redirect('/admin/categories');
    }

    public function delete(string $id): void
    {
        $cid = (int) $id;
        $result = $this->categories->deleteById($cid);
        if (! $result['ok']) {
            Flash::set('error', $result['error']);
            $this->http->redirect('/admin/categories');
        }

        Flash::set('success', 'Kategorie gelöscht. Verknüpfte Bilder haben keine Kategorie mehr (Datenbank).');
        $this->http->redirect('/admin/categories');
    }
}
