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

    /** FULLTEXT n-gram поиск в NATURAL LANGUAGE MODE (устойчив к опечаткам) */
    public function search(string $text, int $limit, int $offset): array
    {
        $q = trim($text);                       // без звёздочек, без дополнительных токенов

        return $this->createQueryBuilder('a')
            ->addSelect('MATCH_AGAINST_NL(a.title, a.artist, :q) AS HIDDEN score')
            ->where('MATCH_AGAINST_NL(a.title, a.artist, :q) > 0')
            ->setParameter('q', $q)
            ->orderBy('score', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countSearch(string $text): int
    {
        $q = trim($text);

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('MATCH_AGAINST_NL(a.title, a.artist, :q) > 0')
            ->setParameter('q', $q)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** fallback – вся база постранично */
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
