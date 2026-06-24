<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Audio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Audio>
 *
 * All read methods take an optional $botId; when set, results are isolated to
 * that tenant (the webhook always passes one).
 */
final class AudioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, Audio::class);
    }

    private function scopeBot(QueryBuilder $qb, ?int $botId): QueryBuilder
    {
        if ($botId !== null) {
            $qb->andWhere('a.bot = :botId')->setParameter('botId', $botId);
        }
        return $qb;
    }

    /** FULLTEXT search with "#Artist" filter, boolean-then-natural fallback. */
    public function search(string $raw, int $limit, int $offset, ?int $botId = null): array
    {
        $raw = trim($raw);

        $artist = null;
        if (preg_match('/#([^#]+?)(?:\s|$)/u', $raw, $m)) {
            $artist = trim($m[1]);
            $raw    = trim(str_replace($m[0], '', $raw));
        }

        $tokens = $raw === '' ? [] : preg_split('/\s+/', $raw);
        $bool   = implode(' ', array_map(fn($t) => '+' . $t . '*', $tokens));

        /* ---------- Stage 1: BOOLEAN MODE ---------- */
        $qb = $this->createQueryBuilder('a')
            ->addSelect('MATCH_AGAINST_BOOL(a.title, a.artist, :bq) AS HIDDEN score')
            ->setParameter('bq', $bool ?: '')
            ->orderBy('score', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $this->scopeBot($qb, $botId);

        if ($artist) {
            $qb->andWhere('a.artist LIKE :art')->setParameter('art', "%$artist%");
        }
        if ($bool) {
            $qb->andWhere('MATCH_AGAINST_BOOL(a.title, a.artist, :bq) > 0');
        }

        $results = $qb->getQuery()->getResult();

        if ($results || $offset || $bool === '') {
            return $results;
        }

        /* ---------- Stage 2: NATURAL (fallback) ---------- */
        $qb = $this->createQueryBuilder('a')
            ->addSelect('MATCH_AGAINST_NL(a.title, a.artist, :nq) AS HIDDEN score')
            ->where('MATCH_AGAINST_NL(a.title, a.artist, :nq) > 0')
            ->setParameter('nq', $raw)
            ->orderBy('score', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $this->scopeBot($qb, $botId);

        if ($artist) {
            $qb->andWhere('a.artist LIKE :art')->setParameter('art', "%$artist%");
        }

        return $qb->getQuery()->getResult();
    }

    public function countSearch(string $raw, ?int $botId = null): int
    {
        $artist = null;
        if (preg_match('/#([^#]+?)(?:\s|$)/u', $raw, $m)) {
            $artist = trim($m[1]);
            $raw    = trim(str_replace($m[0], '', $raw));
        }

        $tokens = $raw === '' ? [] : preg_split('/\s+/', $raw);
        $bool   = implode(' ', array_map(fn($t) => '+' . $t . '*', $tokens));

        $qb = $this->createQueryBuilder('a')->select('COUNT(a.id)');
        $this->scopeBot($qb, $botId);
        if ($bool) {
            $qb->andWhere('MATCH_AGAINST_BOOL(a.title, a.artist, :bq) > 0')->setParameter('bq', $bool);
        }
        if ($artist) {
            $qb->andWhere('a.artist LIKE :art')->setParameter('art', "%$artist%");
        }
        $cnt = (int) $qb->getQuery()->getSingleScalarResult();
        if ($cnt || $bool === '') {
            return $cnt;
        }

        $qb = $this->createQueryBuilder('a')->select('COUNT(a.id)')
            ->where('MATCH_AGAINST_NL(a.title, a.artist, :nq) > 0')
            ->setParameter('nq', $raw);
        $this->scopeBot($qb, $botId);
        if ($artist) {
            $qb->andWhere('a.artist LIKE :art')->setParameter('art', "%$artist%");
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Admin listing for one bot: newest first, optional LIKE filter.
     * @return array{items: Audio[], total: int}
     */
    public function adminPaginate(int $botId, int $page, int $perPage, string $search = ''): array
    {
        $base = $this->createQueryBuilder('a')
            ->where('a.bot = :bot')->setParameter('bot', $botId);
        if ($search !== '') {
            $base->andWhere('a.title LIKE :s OR a.artist LIKE :s')->setParameter('s', '%'.$search.'%');
        }

        $total = (int) (clone $base)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $items = $base->orderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return Audio[] */
    public function findAllPaginated(int $limit, int $offset, ?int $botId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.title')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $this->scopeBot($qb, $botId);

        return $qb->getQuery()->getResult();
    }

    /**
     * Lightweight rows for the in-PHP fuzzy fallback (id + searchable text).
     * @return array<int, array{id:int, name:string}>
     */
    public function searchableRows(?int $botId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select("a.id AS id, CONCAT(a.title, ' ', a.artist) AS name");
        $this->scopeBot($qb, $botId);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Hydrate the given ids preserving their order (Doctrine IN() does not).
     * @param int[] $ids
     * @return Audio[]
     */
    public function findByIdsOrdered(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $rows = $this->createQueryBuilder('a')
            ->where('a.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()->getResult();

        $byId = [];
        foreach ($rows as $a) {
            $byId[$a->getId()] = $a;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }
}
