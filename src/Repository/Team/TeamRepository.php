<?php

declare(strict_types=1);

namespace App\Repository\Team;

use App\Entity\Team\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    public function countPlayersByKingdom(int $kingdomId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.kingdom = :kingdom')
            ->andWhere('t.user IS NOT NULL')
            ->setParameter('kingdom', $kingdomId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Player-managed teams in a kingdom (eligible friendly opponents).
     *
     * @return list<Team>
     */
    public function findPlayerTeamsByKingdom(int $kingdomId, ?int $excludeTeamId = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.kingdom = :kingdom')
            ->andWhere('t.user IS NOT NULL')
            ->setParameter('kingdom', $kingdomId)
            ->orderBy('t.name', SortDirection::Ascending);

        if (null !== $excludeTeamId) {
            $qb->andWhere('t.id != :exclude')
               ->setParameter('exclude', $excludeTeamId);
        }

        return $qb->getQuery()->getResult();
    }

    public function findAvailableNpcTeam(int $kingdomId): ?Team
    {
        $teams = $this->createQueryBuilder('t')
            ->select('t.id')
            ->where('t.kingdom = :kingdom')
            ->andWhere('t.user IS NULL')
            ->andWhere('t.isNpc = true')
            ->setParameter('kingdom', $kingdomId)
            ->getQuery()
            ->getScalarResult();

        if (empty($teams)) {
            return null;
        }

        $randomKey = array_rand($teams);
        $randomId = (int) $teams[$randomKey]['id'];

        return $this->find($randomId);
    }
}
