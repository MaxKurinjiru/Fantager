<?php

declare(strict_types=1);

namespace App\Repository\Kingdom;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KingdomTickLog>
 */
class KingdomTickLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KingdomTickLog::class);
    }

    /**
     * Gets the latest completed scheduledAt datetime for a specific tick type, optionally scoped by team or fixture.
     */
    public function getLatestCompletedTickTime(
        Kingdom $kingdom,
        TickType $tickType,
        ?\App\Entity\Team\Team $team = null,
        ?\App\Entity\League\LeagueFixture $fixture = null,
    ): ?\DateTimeImmutable {
        $qb = $this->createQueryBuilder('l')
            ->where('l.kingdom = :kingdom')
            ->andWhere('l.tickType = :tickType')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('tickType', $tickType)
            ->setParameter('status', 'completed');

        if (null !== $team) {
            $qb->andWhere('l.team = :team')
               ->setParameter('team', $team);
        } else {
            $qb->andWhere('l.team IS NULL');
        }

        if (null !== $fixture) {
            $qb->andWhere('l.fixture = :fixture')
               ->setParameter('fixture', $fixture);
        } else {
            $qb->andWhere('l.fixture IS NULL');
        }

        /** @var KingdomTickLog|null $latest */
        $latest = $qb->orderBy('l.scheduledAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $latest?->getScheduledAt();
    }

    public function hasFailedTicks(Kingdom $kingdom): bool
    {
        $count = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.kingdom = :kingdom')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('status', 'failed')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function hasProcessingTicks(Kingdom $kingdom): bool
    {
        $count = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.kingdom = :kingdom')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('statuses', ['dispatched', 'processing'])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function getOldestPendingTime(Kingdom $kingdom): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('l')
            ->select('MIN(l.scheduledAt)')
            ->where('l.kingdom = :kingdom')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        if (null === $result) {
            return null;
        }

        return new \DateTimeImmutable((string) $result, new \DateTimeZone('UTC'));
    }

    public function getHighestPendingPriorityAt(Kingdom $kingdom, \DateTimeImmutable $time): ?int
    {
        /** @var list<KingdomTickLog> $logs */
        $logs = $this->createQueryBuilder('l')
            ->where('l.kingdom = :kingdom')
            ->andWhere('l.scheduledAt = :time')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('time', $time)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getResult();

        if (empty($logs)) {
            return null;
        }

        $minPriority = null;
        foreach ($logs as $log) {
            $priority = $log->getTickType()->getPriority();
            if (null === $minPriority || $priority < $minPriority) {
                $minPriority = $priority;
            }
        }

        return $minPriority;
    }

    /**
     * @return list<KingdomTickLog>
     */
    public function findPendingTicksInGroup(Kingdom $kingdom, \DateTimeImmutable $time, int $priority): array
    {
        /** @var list<KingdomTickLog> $logs */
        $logs = $this->createQueryBuilder('l')
            ->where('l.kingdom = :kingdom')
            ->andWhere('l.scheduledAt = :time')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('time', $time)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($logs, static function (KingdomTickLog $log) use ($priority): bool {
            return $log->getTickType()->getPriority() === $priority;
        }));
    }
}
