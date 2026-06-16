<?php

declare(strict_types=1);

namespace App\Repository\Graveyard;

use App\Entity\Graveyard\GraveyardRecord;
use App\Entity\Team\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GraveyardRecord>
 */
class GraveyardRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GraveyardRecord::class);
    }

    /**
     * @return list<GraveyardRecord>
     */
    public function findByTeamOrdered(Team $team): array
    {
        /** @var list<GraveyardRecord> $records */
        $records = $this->createQueryBuilder('g')
            ->where('g.team = :team')
            ->setParameter('team', $team)
            ->orderBy('g.dateOfDeath', 'DESC')
            ->addOrderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $records;
    }

    public function countByTeam(Team $team): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.team = :team')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
