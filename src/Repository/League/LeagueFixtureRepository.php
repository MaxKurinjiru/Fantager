<?php

declare(strict_types=1);

namespace App\Repository\League;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\LeagueFixtureStatus;
use App\Service\League\LeagueFixtureKickoffMatcher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LeagueFixture> */
class LeagueFixtureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeagueFixture::class);
    }

    /**
     * @return list<LeagueFixture>
     */
    public function findFixturesInPeriod(Kingdom $kingdom, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.group', 'g')
            ->join('g.tier', 'tier')
            ->join('tier.season', 's')
            ->where('s.kingdom = :kingdom')
            ->andWhere('f.scheduledAt BETWEEN :start AND :end')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    public function findNextFixtureForTeam(Team $team): ?LeagueFixture
    {
        return $this->createQueryBuilder('f')
            ->where('(f.homeTeam = :team OR f.awayTeam = :team)')
            ->andWhere('f.status = :status')
            ->setParameter('team', $team)
            ->setParameter('status', LeagueFixtureStatus::Scheduled)
            ->orderBy('f.scheduledAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<LeagueFixture>
     */
    public function findFixturesForTeamInPeriod(Team $team, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('f')
            ->where('(f.homeTeam = :team OR f.awayTeam = :team)')
            ->andWhere('f.scheduledAt BETWEEN :start AND :end')
            ->setParameter('team', $team)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('f.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findNextHomeFixtureForTeam(Team $team): ?LeagueFixture
    {
        return $this->createQueryBuilder('f')
            ->where('f.homeTeam = :team')
            ->andWhere('f.status = :status')
            ->setParameter('team', $team)
            ->setParameter('status', LeagueFixtureStatus::Scheduled)
            ->orderBy('f.scheduledAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Scheduled fixtures for a league-match tick instant.
     *
     * Ticks are stored in UTC (kingdom-local 18:00 converted). Older fixtures were stored
     * as naive wall-clock UTC (18:00 without conversion) — both shapes are matched.
     *
     * @return list<LeagueFixture>
     */
    public function findScheduledFixturesAtTime(Kingdom $kingdom, \DateTimeImmutable $scheduledAt): array
    {
        [$utcInstant, $legacyWallClockUtc] = LeagueFixtureKickoffMatcher::resolveMatchCandidates($kingdom, $scheduledAt);

        $qb = $this->createQueryBuilder('f')
            ->join('f.homeTeam', 'home')
            ->where('home.kingdom = :kingdom')
            ->andWhere('f.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('status', LeagueFixtureStatus::Scheduled);

        if ($utcInstant == $legacyWallClockUtc) {
            $qb->andWhere('f.scheduledAt = :scheduledAt')
                ->setParameter('scheduledAt', $utcInstant);
        } else {
            $qb->andWhere('f.scheduledAt = :utcInstant OR f.scheduledAt = :legacyWallClockUtc')
                ->setParameter('utcInstant', $utcInstant)
                ->setParameter('legacyWallClockUtc', $legacyWallClockUtc);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * All scheduled fixtures whose kickoff is at or before the given instant.
     *
     * @return list<LeagueFixture>
     */
    public function findPendingFixturesUntil(Kingdom $kingdom, \DateTimeImmutable $until): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.homeTeam', 'home')
            ->where('home.kingdom = :kingdom')
            ->andWhere('f.status = :status')
            ->andWhere('f.scheduledAt <= :until')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('status', LeagueFixtureStatus::Scheduled)
            ->setParameter('until', $until)
            ->orderBy('f.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Completed fixtures that still reference a temporary formation assignment.
     *
     * @return list<LeagueFixture>
     */
    public function findCompletedWithTemporaryAssignments(Kingdom $kingdom): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.group', 'g')
            ->join('g.tier', 'tier')
            ->join('tier.season', 's')
            ->leftJoin('f.homeFormation', 'hf')
            ->leftJoin('f.awayFormation', 'af')
            ->where('s.kingdom = :kingdom')
            ->andWhere('f.status = :completed')
            ->andWhere('hf.isTemporary = true OR af.isTemporary = true')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('completed', LeagueFixtureStatus::Completed)
            ->getQuery()
            ->getResult();
    }
}
