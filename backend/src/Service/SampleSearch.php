<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Audio;
use App\Repository\AudioRepository;

/**
 * Search cascade: exact FULLTEXT → keyboard-layout retry → in-PHP fuzzy fallback.
 * The fuzzy stage only fires on an otherwise-empty first page, so normal queries
 * pay nothing for it.
 */
final class SampleSearch
{
    public function __construct(
        private readonly AudioRepository $repo,
        private readonly FuzzySearch     $fuzzy,
    ) {}

    /** @return Audio[] */
    public function search(string $query, int $limit, int $offset, ?int $botId = null): array
    {
        $query = trim($query);

        // 1) Exact FULLTEXT (also handles the "#artist" filter + boolean/natural).
        $exact = $this->repo->search($query, $limit, $offset, $botId);
        if ($exact || $query === '' || $offset > 0) {
            return $exact;
        }

        // 2) Wrong keyboard layout (Latin typed in RU layout) → retry exact.
        $swapped = $this->fuzzy->swapLayout($query);
        if ($swapped !== null) {
            $alt = $this->repo->search($swapped, $limit, 0, $botId);
            if ($alt) {
                return $alt;
            }
        }

        // 3) Typo-tolerant fuzzy over the catalog (page-0 only).
        $ids = $this->fuzzy->match($query, $this->repo->searchableRows($botId));
        if (!$ids) {
            return [];
        }

        return $this->repo->findByIdsOrdered(array_slice($ids, 0, $limit));
    }
}
