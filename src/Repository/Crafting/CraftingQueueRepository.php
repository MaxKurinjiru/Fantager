<?php

declare(strict_types=1);

namespace App\Repository\Crafting;

use App\Entity\Crafting\CraftingQueue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CraftingQueueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CraftingQueue::class);
    }

    /**
     * @return list<CraftingQueue>
     */
    public function findDueJobs(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.status = :status')
            ->andWhere('q.completesAt <= :now')
            ->setParameter('status', \App\Enum\CraftingStatus::InProgress)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
