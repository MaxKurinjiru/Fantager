<?php

declare(strict_types=1);

namespace App\Repository\Team;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Entity\Team\TeamSummonHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamSummonHistory>
 */
class TeamSummonHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamSummonHistory::class);
    }

    /**
     * @return list<TeamSummonHistory>
     */
    public function findByTeamFiltered(Team $team, ?string $race = null, ?string $sort = null, ?int $page = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.team = :team')
            ->setParameter('team', $team);

        if (null !== $race && '' !== $race) {
            $qb->andWhere('s.raceSelected = :race')
               ->setParameter('race', $race);
        }

        switch ($sort) {
            case 'date-asc':
                $qb->orderBy('s.summonedAt', 'ASC');
                break;
            case 'cost-desc':
                $qb->orderBy('s.goldCost', 'DESC');
                break;
            case 'cost-asc':
                $qb->orderBy('s.goldCost', 'ASC');
                break;
            case 'date-desc':
            default:
                $qb->orderBy('s.summonedAt', 'DESC');
                break;
        }

        if (null !== $page && null !== $limit) {
            $qb->setFirstResult(($page - 1) * $limit)
               ->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByTeamFiltered(Team $team, ?string $race = null): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.team = :team')
            ->setParameter('team', $team);

        if (null !== $race && '' !== $race) {
            $qb->andWhere('s.raceSelected = :race')
               ->setParameter('race', $race);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findOneByHero(Hero $hero): ?TeamSummonHistory
    {
        $result = $this->findOneBy(['hero' => $hero], ['summonedAt' => 'DESC']);

        return $result instanceof TeamSummonHistory ? $result : null;
    }
}
