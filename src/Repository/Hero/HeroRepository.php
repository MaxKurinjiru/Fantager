<?php

declare(strict_types=1);

namespace App\Repository\Hero;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Hero>
 */
class HeroRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hero::class);
    }

    public function countCombatReadyByTeam(Team $team): int
    {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.team = :team')
            ->andWhere('h.role = :role')
            ->andWhere('h.status = :status')
            ->setParameter('team', $team)
            ->setParameter('role', HeroRole::Combatant)
            ->setParameter('status', HeroStatus::Available)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Hero>
     */
    public function findCombatantsByTeam(Team $team): array
    {
        /** @var list<Hero> $heroes */
        $heroes = $this->findBy(['team' => $team, 'role' => HeroRole::Combatant]);

        return $heroes;
    }

    /**
     * @return list<Hero>
     */
    public function findTrainersByTeam(Team $team): array
    {
        /** @var list<Hero> $trainers */
        $trainers = $this->findBy(['team' => $team, 'role' => HeroRole::Trainer]);

        return $trainers;
    }

    public function countTrainersByTeam(Team $team): int
    {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.team = :team')
            ->andWhere('h.role = :role')
            ->setParameter('team', $team)
            ->setParameter('role', HeroRole::Trainer)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Hero>
     */
    public function findPayrollEligibleByTeam(Team $team): array
    {
        /** @var list<Hero> $heroes */
        $heroes = $this->createQueryBuilder('h')
            ->where('h.team = :team')
            ->andWhere('h.status NOT IN (:excludedStatuses)')
            ->setParameter('team', $team)
            ->setParameter('excludedStatuses', [HeroStatus::Dead, HeroStatus::Retired])
            ->getQuery()
            ->getResult();

        return $heroes;
    }
}
