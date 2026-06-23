<?php
namespace App\Repository;

use App\Entity\Audio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AudioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, Audio::class);
    }

    /** Поиск с фильтром “#Автор” + опечатки ±1 символ */
    public function search(string $raw, int $limit, int $offset): array
    {
        $raw = trim($raw);

        /* --- автор через # --- */
        $artist = null;
        if (preg_match('/#([^#]+?)(?:\s|$)/u', $raw, $m)) {
            $artist = trim($m[1]);                 // «Дядя Саша»
            $raw    = trim(str_replace($m[0], '', $raw));
        }

        /* --- токены для BOOLEAN-поиска --- */
        $tokens = $raw === '' ? [] : preg_split('/\s+/', $raw);
        $bool   = implode(' ', array_map(fn($t) => '+' . $t . '*', $tokens));

        /* ---------- ЭТАП 1 : BOOLEAN MODE ----------- */
        $qb = $this->createQueryBuilder('a')
            ->addSelect('MATCH_AGAINST_BOOL(a.title, a.artist, :bq) AS HIDDEN score')
            ->setParameter('bq', $bool ?: '')
            ->orderBy('score', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($artist) {
            $qb->andWhere('a.artist LIKE :art')->setParameter('art', "%$artist%");
        }
        if ($bool) {
            $qb->andWhere('MATCH_AGAINST_BOOL(a.title, a.artist, :bq) > 0');
        }

        $results = $qb->getQuery()->getResult();

        /* если что-то нашли или paginated – возвращаем */
        if ($results || $offset || $bool === '') {
            return $results;
        }

        /* ---------- ЭТАП 2 : NATURAL (fallback) ------ */
        $qb = $this->createQueryBuilder('a')
            ->addSelect('MATCH_AGAINST_NL(a.title, a.artist, :nq) AS HIDDEN score')
            ->where('MATCH_AGAINST_NL(a.title, a.artist, :nq) > 0')
            ->setParameter('nq', $raw)
            ->orderBy('score', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($artist) {
            $qb->andWhere('a.artist LIKE :art')->setParameter('art', "%$artist%");
        }

        return $qb->getQuery()->getResult();
    }

    public function countSearch(string $raw): int
    {
        $artist = null;
        if (preg_match('/#([^#]+?)(?:\s|$)/u', $raw, $m)) {
            $artist = trim($m[1]);
            $raw    = trim(str_replace($m[0], '', $raw));
        }

        $tokens = $raw === '' ? [] : preg_split('/\s+/', $raw);
        $bool   = implode(' ', array_map(fn($t) => '+' . $t . '*', $tokens));

        /* primary count */
        $qb = $this->createQueryBuilder('a')->select('COUNT(a.id)');
        if ($bool) {
            $qb->where('MATCH_AGAINST_BOOL(a.title, a.artist, :bq) > 0')
               ->setParameter('bq', $bool);
        }
        if ($artist) {
            $qb->andWhere('a.artist LIKE :art')->setParameter('art', "%$artist%");
        }
        $cnt = (int)$qb->getQuery()->getSingleScalarResult();
        if ($cnt || $bool === '') {
            return $cnt;
        }

        /* fallback count */
        $qb = $this->createQueryBuilder('a')->select('COUNT(a.id)')
            ->where('MATCH_AGAINST_NL(a.title, a.artist, :nq) > 0')
            ->setParameter('nq', $raw);
        if ($artist) {
            $qb->andWhere('a.artist LIKE :art')->setParameter('art', "%$artist%");
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    public function findAllPaginated(int $limit, int $offset): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.title')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
