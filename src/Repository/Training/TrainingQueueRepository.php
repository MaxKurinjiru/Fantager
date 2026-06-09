<?php

declare(strict_types=1);

namespace App\Repository\Training;

use App\Entity\Training\TrainingQueue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TrainingQueueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingQueue::class);
    }

    /**
     * @return list<TrainingQueue>
     */
    public function findPendingDue(\DateTimeImmutable $now, ?\App\Entity\Kingdom\Kingdom $kingdom = null): array
    {
        $qb = $this->createQueryBuilder('q')
            ->where('q.status = :status')
            ->andWhere('q.executeAt <= :now')
            ->setParameter('status', \App\Enum\TrainingStatus::Pending)
            ->setParameter('now', $now);

        if (null !== $kingdom) {
            $qb->join('q.hero', 'h')
                ->join('h.team', 't')
                ->andWhere('t.kingdom = :kingdom')
                ->setParameter('kingdom', $kingdom);
        }

        return $qb->getQuery()->getResult();
    }

    public function countPendingForHero(\App\Entity\Hero\Hero $hero): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.hero = :hero')
            ->andWhere('q.status = :status')
            ->setParameter('hero', $hero)
            ->setParameter('status', \App\Enum\TrainingStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<TrainingQueue>
     */
    public function findJobsInPeriodForTeam(int $teamId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('q')
            ->join('q.hero', 'h')
            ->where('h.team = :teamId')
            ->andWhere('q.executeAt BETWEEN :start AND :end')
            ->setParameter('teamId', $teamId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }
}
