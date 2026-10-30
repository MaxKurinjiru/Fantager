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
    public function findCombatReadyByTeam(Team $team): array
    {
        /** @var list<Hero> $heroes */
        $heroes = $this->createQueryBuilder('h')
            ->where('h.team = :team')
            ->andWhere('h.role = :role')
            ->andWhere('h.status = :status')
            ->setParameter('team', $team)
            ->setParameter('role', HeroRole::Combatant)
            ->setParameter('status', HeroStatus::Available)
            ->orderBy('h.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $heroes;
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
        $trainers = $this->createQueryBuilder('h')
            ->where('h.team = :team')
            ->andWhere('h.role = :role')
            ->andWhere('h.status NOT IN (:deadStatuses)')
            ->setParameter('team', $team)
            ->setParameter('role', HeroRole::Trainer)
            ->setParameter('deadStatuses', [HeroStatus::Dead, HeroStatus::Retired])
            ->getQuery()
            ->getResult();

        return $trainers;
    }

    public function countTrainersByTeam(Team $team): int
    {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.team = :team')
            ->andWhere('h.role = :role')
            ->andWhere('h.status NOT IN (:deadStatuses)')
            ->setParameter('team', $team)
            ->setParameter('role', HeroRole::Trainer)
            ->setParameter('deadStatuses', [HeroStatus::Dead, HeroStatus::Retired])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveCombatantsByTeam(Team $team): int
    {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.team = :team')
            ->andWhere('h.role = :role')
            ->andWhere('h.status NOT IN (:deadStatuses)')
            ->setParameter('team', $team)
            ->setParameter('role', HeroRole::Combatant)
            ->setParameter('deadStatuses', [HeroStatus::Dead, HeroStatus::Retired])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveTrainersByTeam(Team $team): int
    {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.team = :team')
            ->andWhere('h.role = :role')
            ->andWhere('h.status NOT IN (:deadStatuses)')
            ->setParameter('team', $team)
            ->setParameter('role', HeroRole::Trainer)
            ->setParameter('deadStatuses', [HeroStatus::Dead, HeroStatus::Retired])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Stream heroes (with team) for bulk jobs. Optional kingdom filter.
     *
     * @return iterable<Hero>
     */
    public function iterateWithTeam(?int $kingdomId = null): iterable
    {
        $qb = $this->createQueryBuilder('h')
            ->innerJoin('h.team', 't')
            ->addSelect('t')
            ->orderBy('h.id', \SortDirection::Ascending);

        if (null !== $kingdomId) {
            $qb->andWhere('t.kingdom = :kingdomId')
                ->setParameter('kingdomId', $kingdomId);
        }

        /** @var iterable<Hero> $heroes */
        $heroes = $qb->getQuery()->toIterable();

        return $heroes;
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
