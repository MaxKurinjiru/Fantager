<?php

declare(strict_types=1);

namespace App\Repository\League;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueStanding;
use App\Enum\LeagueSeasonStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LeagueStanding> */
class LeagueStandingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeagueStanding::class);
    }

    /**
     * @return array<int, LeagueStanding> team ID => standing
     */
    public function findIndexedByTeamForActiveSeason(Kingdom $kingdom): array
    {
        /** @var list<LeagueStanding> $standings */
        $standings = $this->createQueryBuilder('ls')
            ->innerJoin('ls.group', 'lg')
            ->innerJoin('lg.tier', 'lt')
            ->innerJoin('lt.season', 's')
            ->addSelect('lg', 'lt')
            ->where('s.kingdom = :kingdom')
            ->andWhere('s.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('status', LeagueSeasonStatus::Active)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($standings as $standing) {
            $teamId = $standing->getTeam()->getId();
            if (null !== $teamId) {
                $indexed[$teamId] = $standing;
            }
        }

        return $indexed;
    }
}
