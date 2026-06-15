<?php

declare(strict_types=1);

namespace App\Repository\League;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\LeagueFixtureStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LeagueFixtureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeagueFixture::class);
    }

    /**
     * @return list<LeagueFixture>
     */
    public function findFixturesInPeriod(\App\Entity\Kingdom\Kingdom $kingdom, \DateTimeImmutable $start, \DateTimeImmutable $end): array
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
     * @return list<LeagueFixture>
     */
    public function findScheduledFixturesAtTime(Kingdom $kingdom, \DateTimeImmutable $scheduledAt): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.homeTeam', 'home')
            ->where('home.kingdom = :kingdom')
            ->andWhere('f.scheduledAt = :scheduledAt')
            ->andWhere('f.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('scheduledAt', $scheduledAt)
            ->setParameter('status', LeagueFixtureStatus::Scheduled)
            ->getQuery()
            ->getResult();
    }
}
