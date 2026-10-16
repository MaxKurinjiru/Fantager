<?php

declare(strict_types=1);

namespace App\Repository\FriendlyMatch;

use App\Entity\FriendlyMatch\FriendlyMatch;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FriendlyMatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<FriendlyMatch> */
class FriendlyMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FriendlyMatch::class);
    }

    /**
     * Find all scheduled (status=scheduled) friendly matches for a kingdom at a specific time.
     *
     * @return list<FriendlyMatch>
     */
    public function findScheduledAtTime(Kingdom $kingdom, \DateTimeImmutable $scheduledAt): array
    {
        return $this->createQueryBuilder('fm')
            ->where('fm.kingdom = :kingdom')
            ->andWhere('fm.scheduledAt = :scheduledAt')
            ->andWhere('fm.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('scheduledAt', $scheduledAt)
            ->setParameter('status', FriendlyMatchStatus::Scheduled)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count home friendly matches for a team in a given week (for the home cap rule).
     *
     * @param list<FriendlyMatchStatus> $statuses
     */
    public function countHomeInWeek(Team $team, \DateTimeImmutable $weekStart, \DateTimeImmutable $weekEnd, array $statuses): int
    {
        return (int) $this->createQueryBuilder('fm')
            ->select('COUNT(fm.id)')
            ->where('fm.homeTeam = :team')
            ->andWhere('fm.scheduledAt >= :weekStart')
            ->andWhere('fm.scheduledAt <= :weekEnd')
            ->andWhere('fm.status IN (:statuses)')
            ->setParameter('team', $team)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find a conflict: a friendly match where either team already has an active match at the given time.
     *
     * @param list<FriendlyMatchStatus> $statuses
     */
    public function findTeamConflictAtTime(
        Team $teamA,
        Team $teamB,
        \DateTimeImmutable $scheduledAt,
        array $statuses,
    ): ?FriendlyMatch {
        return $this->createQueryBuilder('fm')
            ->where('fm.scheduledAt = :scheduledAt')
            ->andWhere('fm.status IN (:statuses)')
            ->andWhere(
                '(fm.homeTeam = :teamA OR fm.awayTeam = :teamA OR fm.homeTeam = :teamB OR fm.awayTeam = :teamB)'
            )
            ->setParameter('scheduledAt', $scheduledAt)
            ->setParameter('statuses', $statuses)
            ->setParameter('teamA', $teamA)
            ->setParameter('teamB', $teamB)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find friendly matches for a kingdom, optionally filtered by status.
     *
     * @return list<FriendlyMatch>
     */
    public function findForKingdom(Kingdom $kingdom, ?FriendlyMatchStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('fm')
            ->where('fm.kingdom = :kingdom')
            ->setParameter('kingdom', $kingdom)
            ->orderBy('fm.scheduledAt', SortDirection::Descending);

        if (null !== $status) {
            $qb->andWhere('fm.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find friendly matches for a team (as home or away) in a time period.
     * Used for the calendar feed.
     *
     * @return list<FriendlyMatch>
     */
    public function findForTeamInPeriod(int $teamId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('fm')
            ->where('(IDENTITY(fm.homeTeam) = :teamId OR IDENTITY(fm.awayTeam) = :teamId)')
            ->andWhere('fm.scheduledAt BETWEEN :start AND :end')
            ->setParameter('teamId', $teamId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('fm.scheduledAt', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
