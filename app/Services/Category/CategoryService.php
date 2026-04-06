<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Category;

use BSPhotoGalerie\Core\Request;
use BSPhotoGalerie\Services\Domain\CategoryAdminService;

/**
 * Kategorie-Anwendungslogik aus HTTP-Request (Roadmap „Service Layer“); Persistenz/Regeln in {@see CategoryAdminService}.
 */
final class CategoryService
{
    public function __construct(
        private CategoryAdminService $categories
    ) {
    }

    /**
     * @return array{ok:true}|array{ok:false, error:string}
     */
    public function createFromRequest(Request $request): array
    {
        $name = (string) $request->post('name', '');
        $slugInput = (string) $request->post('slug', '');
        $isPublic = $request->post('is_public', '') === '1';

        return $this->categories->create($name, $slugInput, $isPublic);
    }

    /**
     * @return array{ok:true}|array{ok:false, error:string}
     */
    public function updateFromRequest(int $categoryId, Request $request): array
    {
        $name = (string) $request->post('name', '');
        $slugInput = (string) $request->post('slug', '');
        $isPublic = $request->post('is_public', '') === '1';

        return $this->categories->update($categoryId, $name, $slugInput, $isPublic);
    }

    /**
     * @return array{ok:true}|array{ok:false, error:string}
     */
    public function deleteById(int $categoryId): array
    {
        return $this->categories->delete($categoryId);
    }
}
