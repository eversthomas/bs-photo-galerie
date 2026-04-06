<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Domain;

use BSPhotoGalerie\Core\SlugGenerator;
use BSPhotoGalerie\Models\CategoryRepository;

/**
 * Anwendungslogik für Kategorien im Backend (ohne HTTP).
 */
final class CategoryAdminService
{
    public function __construct(
        private CategoryRepository $categories
    ) {
    }

    public function uniqueSlug(string $slug, ?int $exceptId): string
    {
        $candidate = $slug;
        $n = 2;
        while ($this->categories->slugExists($candidate, $exceptId)) {
            $candidate = $slug . '-' . $n;
            ++$n;
        }

        return mb_substr($candidate, 0, 255);
    }

    /**
     * @return array{ok:true}|array{ok:false, error:string}
     */
    public function create(string $name, string $slugInput, bool $isPublic): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Bitte einen Namen eingeben.'];
        }

        $base = trim($slugInput) !== '' ? trim($slugInput) : $name;
        $slug = $this->uniqueSlug(SlugGenerator::slugify($base), null);
        $id = $this->categories->insert(mb_substr($name, 0, 255), $slug, $isPublic);
        if ($id < 1) {
            return ['ok' => false, 'error' => 'Speichern fehlgeschlagen.'];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok:true}|array{ok:false, error:string}
     */
    public function update(int $cid, string $name, string $slugInput, bool $isPublic): array
    {
        if ($this->categories->findById($cid) === null) {
            return ['ok' => false, 'error' => 'Kategorie nicht gefunden.'];
        }

        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Bitte einen Namen eingeben.'];
        }

        $base = trim($slugInput) !== '' ? trim($slugInput) : $name;
        $slug = $this->uniqueSlug(SlugGenerator::slugify($base), $cid);
        $this->categories->update($cid, mb_substr($name, 0, 255), $slug, $isPublic);

        return ['ok' => true];
    }

    /**
     * @return array{ok:true}|array{ok:false, error:string}
     */
    public function delete(int $cid): array
    {
        if ($this->categories->findById($cid) === null) {
            return ['ok' => false, 'error' => 'Kategorie nicht gefunden.'];
        }
        $this->categories->delete($cid);

        return ['ok' => true];
    }
}
