<?php

declare(strict_types=1);

namespace App\Repository\Summoning;

use App\Entity\Summoning\SummonHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SummonHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SummonHistory::class);
    }

    /**
     * @return list<SummonHistory>
     */
    public function findByTeamFiltered(\App\Entity\Team\Team $team, ?string $race = null, ?string $sort = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.team = :team')
            ->setParameter('team', $team);

        if ($race !== null && $race !== '') {
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

        return $qb->getQuery()->getResult();
    }
}
