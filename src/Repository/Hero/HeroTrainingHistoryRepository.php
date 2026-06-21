<?php

declare(strict_types=1);

namespace App\Repository\Hero;

use App\Entity\Hero\HeroTrainingHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeroTrainingHistory>
 */
class HeroTrainingHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeroTrainingHistory::class);
    }

    /**
     * @return list<HeroTrainingHistory>
     */
    public function findInPeriodForTeam(int $teamId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('h')
            ->join('h.hero', 'hero')
            ->where('hero.team = :teamId')
            ->andWhere('h.completedAt BETWEEN :start AND :end')
            ->setParameter('teamId', $teamId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }
}
