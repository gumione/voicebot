<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Bot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bot>
 */
final class BotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bot::class);
    }

    public function findActiveByWebhookToken(string $token): ?Bot
    {
        return $this->findOneBy(['webhookToken' => $token, 'isActive' => true]);
    }

    /**
     * @return array{items: Bot[], total: int}
     */
    public function paginate(int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('b')->orderBy('b.createdAt', 'DESC');
        $total = (int) (clone $qb)->select('COUNT(b.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
