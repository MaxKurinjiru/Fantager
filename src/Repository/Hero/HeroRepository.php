<?php

declare(strict_types=1);

namespace App\Repository\Hero;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
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
            ->andWhere('h.status = :status')
            ->setParameter('team', $team)
            ->setParameter('status', HeroStatus::Available)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
