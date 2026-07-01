<?php

declare(strict_types=1);

namespace App\Repository\Team;

use App\Entity\Team\Team;
use App\Entity\Team\TeamDailySnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamDailySnapshot>
 */
class TeamDailySnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamDailySnapshot::class);
    }

    /**
     * @return list<TeamDailySnapshot>
     */
    public function findLastMonthHistory(Team $team): array
    {
        $limitDate = (new \DateTimeImmutable('-30 days'))->setTime(0, 0, 0);

        return $this->createQueryBuilder('s')
            ->where('s.team = :team')
            ->andWhere('s.recordedAt >= :limitDate')
            ->setParameter('team', $team)
            ->setParameter('limitDate', $limitDate)
            ->orderBy('s.recordedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function deleteOlderThan(Team $team, \DateTimeImmutable $date): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->where('s.team = :team')
            ->andWhere('s.recordedAt < :limitDate')
            ->setParameter('team', $team)
            ->setParameter('limitDate', $date->setTime(0, 0, 0))
            ->getQuery()
            ->execute();
    }
}
