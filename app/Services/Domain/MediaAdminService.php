<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Domain;

use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Models\MediaRepository;

/**
 * Backend-Hilfen für Medienlisten (Sortierung, Kategorie-Zuordnung, Filter).
 */
final class MediaAdminService
{
    /** @var array<string, string> */
    public const PERIOD_LABELS = [
        'all' => 'Alle',
        'hour' => 'Letzte Stunde',
        'day' => 'Letzte 7 Tage',
        'week' => 'Letzte 4 Wochen',
        'month' => 'Letzte 12 Monate',
    ];

    public function __construct(
        private MediaRepository $media,
        private CategoryRepository $categories
    ) {
    }

    public function normalizePeriod(string $raw): string
    {
        return isset(self::PERIOD_LABELS[$raw]) ? $raw : 'all';
    }

    public function periodQuery(string $period): string
    {
        return $period === 'all' ? '' : ('?period=' . rawurlencode($period));
    }

    public function normalizeListSort(string $raw): string
    {
        return match ($raw) {
            'upload', 'captured' => $raw,
            default => 'manual',
        };
    }

    public function normalizeListDir(string $raw): string
    {
        return strtolower($raw) === 'asc' ? 'asc' : 'desc';
    }

    /**
     * Query-String für /admin/media inkl. Zeitraum und Listensortierung (?period=&sort=&dir=).
     */
    public function adminMediaIndexQuery(string $period, string $sort, string $dir): string
    {
        $period = $this->normalizePeriod($period);
        $sort = $this->normalizeListSort($sort);
        $dir = $this->normalizeListDir($dir);
        $q = [];
        if ($period !== 'all') {
            $q['period'] = $period;
        }
        if ($sort !== 'manual') {
            $q['sort'] = $sort;
            $q['dir'] = $dir;
        }

        return $q === [] ? '' : ('?' . http_build_query($q));
    }

    /**
     * @return list<int>
     */
    public function parseBulkIdsFromPost(mixed $rawIds): array
    {
        $ids = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $v) {
                if (is_string($v) && ctype_digit($v)) {
                    $ids[] = (int) $v;
                } elseif (is_int($v) && $v > 0) {
                    $ids[] = $v;
                }
            }
        }

        return array_slice(array_values(array_unique($ids)), 0, 200);
    }

    public function resolveCategoryId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_string($raw) && ! is_int($raw)) {
            return null;
        }
        $s = (string) $raw;
        if (! ctype_digit($s)) {
            return null;
        }
        $id = (int) $s;
        foreach ($this->categories->listAllOrdered() as $row) {
            if ($row['id'] === $id) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @return array{ok:true, query:string}|array{ok:false, error:string, query:string}
     */
    /**
     * @param string $listSortRaw Query/POST-Wert: manual|upload|captured
     */
    public function reorderFromPost(
        string $periodRaw,
        mixed $orderPost,
        string $listSortRaw,
        string $listDirRaw
    ): array {
        $period = $this->normalizePeriod($periodRaw);
        $listSort = $this->normalizeListSort($listSortRaw);
        $listDir = $this->normalizeListDir($listDirRaw);
        $q = $this->adminMediaIndexQuery($period, $listSort, $listDir);

        if (! is_string($orderPost) || trim($orderPost) === '') {
            return ['ok' => false, 'error' => 'Keine Reihenfolge übermittelt.', 'query' => $q];
        }

        if ($period !== 'all') {
            return [
                'ok' => false,
                'error' => 'Reihenfolge ist nur in der Ansicht „Alle“ möglich.',
                'query' => $q,
            ];
        }

        $parts = array_map('trim', explode(',', $orderPost));
        $ids = [];
        foreach ($parts as $p) {
            if ($p !== '' && ctype_digit($p)) {
                $ids[] = (int) $p;
            }
        }

        $items = $this->media->listByUploadPeriod('all', 200, 0, $listSort, $listDir);
        $expected = array_map(static fn ($m) => $m->id, $items);
        $expectedSorted = $expected;
        sort($expectedSorted);
        $gotSorted = $ids;
        sort($gotSorted);

        if ($expectedSorted !== $gotSorted || count($ids) !== count($expected)) {
            return [
                'ok' => false,
                'error' => 'Sortierung ungültig (Kontext hat sich geändert). Bitte Seite neu laden.',
                'query' => $q,
            ];
        }

        $this->media->reorderByOrderedIds($ids);

        return ['ok' => true, 'query' => $q];
    }

    /**
     * @param list<int> $ids
     *
     * @return array{ok:true, updated:int, categoryId:?int}|array{ok:false, error:string, query:string}
     */
    /**
     * @param string $listSortRaw manual|upload|captured
     */
    public function bulkAssignCategoryFromPost(
        array $ids,
        mixed $categoryRaw,
        string $periodRaw,
        string $listSortRaw,
        string $listDirRaw
    ): array {
        $period = $this->normalizePeriod($periodRaw);
        $listSort = $this->normalizeListSort($listSortRaw);
        $listDir = $this->normalizeListDir($listDirRaw);
        $q = $this->adminMediaIndexQuery($period, $listSort, $listDir);

        if ($ids === []) {
            return ['ok' => false, 'error' => 'Bitte mindestens ein Bild auswählen.', 'query' => $q];
        }

        $categoryId = $this->resolveCategoryId($categoryRaw);
        $updated = $this->media->bulkAssignCategory($ids, $categoryId);

        return ['ok' => true, 'updated' => $updated, 'categoryId' => $categoryId, 'query' => $q];
    }
}
