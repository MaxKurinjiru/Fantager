<?php

declare(strict_types=1);

namespace App\Repository\Graveyard;

use App\Entity\Graveyard\StaffRecord;
use App\Entity\Team\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StaffRecord>
 */
class StaffRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StaffRecord::class);
    }

    /**
     * @return list<StaffRecord>
     */
    public function findByTeamOrdered(Team $team): array
    {
        /** @var list<StaffRecord> $records */
        $records = $this->createQueryBuilder('s')
            ->where('s.team = :team')
            ->setParameter('team', $team)
            ->orderBy('s.dateOfDeparture', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $records;
    }
}
