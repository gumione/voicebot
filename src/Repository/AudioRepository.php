<?php

namespace App\Repository;

use App\Entity\Audio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AudioRepository extends ServiceEntityRepository {

    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Audio::class);
    }

    public function findByTitleWithKeyword(string $keyword, $limit = null, $offset = null) {
        $keywords = explode(' ', $keyword);
        $parameters = [];

        $qb = $this->createQueryBuilder('a');
        $i = 0;

        foreach ($keywords as $word) {
            $qb->addSelect("LEVENSHTEIN(a.title, :word{$i}) AS HIDDEN lev{$i}")
                    ->andWhere("LOCATE(:word{$i}, a.title) > 0 OR SOUNDEX(a.title) = SOUNDEX(:word{$i})");
            $parameters["word{$i}"] = $word;
            $i++;
        }

        $qb->setMaxResults($limit)
                ->setFirstResult($offset)
                ->setParameters($parameters)
                ->orderBy("lev0", 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function countByTitleWithKeyword(string $keyword) {
        $keywords = explode(' ', $keyword);
        $parameters = [];

        $qb = $this->createQueryBuilder('a')
                ->select('count(a.id)');
        $i = 0;

        foreach ($keywords as $word) {
            $qb->andWhere("LOCATE(:word{$i}, a.title) > 0 OR SOUNDEX(a.title) = SOUNDEX(:word{$i})");
            $parameters["word{$i}"] = $word;
            $i++;
        }

        $qb->setParameters($parameters);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function countAll() {
        return $this->createQueryBuilder('a')
                        ->select('count(a.id)')
                        ->getQuery()
                        ->getSingleScalarResult();
    }

}
