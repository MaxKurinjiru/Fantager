<?php

declare(strict_types=1);

namespace App\Repository\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    /** @return list<Formation> */
    public function findSavedByTeam(Team $team): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.team = :team')
            ->andWhere('f.isTemporary = false')
            ->setParameter('team', $team)
            ->orderBy('f.isDefault', 'DESC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDefaultForTeam(Team $team): ?Formation
    {
        return $this->findOneBy(['team' => $team, 'isDefault' => true, 'isTemporary' => false]);
    }

    public function countSavedByTeam(Team $team): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.team = :team')
            ->andWhere('f.isTemporary = false')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Formation> */
    public function findTemporaryByFixture(\App\Entity\League\LeagueFixture $fixture): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.sourceFixture = :fixture')
            ->andWhere('f.isTemporary = true')
            ->setParameter('fixture', $fixture)
            ->getQuery()
            ->getResult();
    }

    /**
     * Temporary formations whose source fixture is already completed (orphans).
     *
     * @return list<Formation>
     */
    public function findTemporaryWithCompletedSourceFixture(Kingdom $kingdom): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.sourceFixture', 'fixture')
            ->join('fixture.group', 'g')
            ->join('g.tier', 'tier')
            ->join('tier.season', 's')
            ->where('s.kingdom = :kingdom')
            ->andWhere('f.isTemporary = true')
            ->andWhere('fixture.status = :completed')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('completed', \App\Enum\LeagueFixtureStatus::Completed)
            ->getQuery()
            ->getResult();
    }
}
