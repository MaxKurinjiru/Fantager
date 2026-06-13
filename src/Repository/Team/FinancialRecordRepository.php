<?php

declare(strict_types=1);

namespace App\Repository\Team;

use App\Entity\Team\FinancialRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FinancialRecord>
 */
class FinancialRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinancialRecord::class);
    }

    /**
     * @return list<FinancialRecord>
     */
    public function findByTeamFiltered(\App\Entity\Team\Team $team, ?string $type = null, ?string $actor = null, ?string $sort = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.team = :team')
            ->setParameter('team', $team);

        if ($type !== null && $type !== '') {
            $qb->andWhere('f.type = :type')
               ->setParameter('type', $type);
        }

        if ($actor !== null && $actor !== '') {
            $qb->andWhere('f.actor = :actor')
               ->setParameter('actor', $actor);
        }

        switch ($sort) {
            case 'date-asc':
                $qb->orderBy('f.createdAt', 'ASC');
                break;
            case 'amount-desc':
                $qb->orderBy('f.goldChange', 'DESC');
                break;
            case 'amount-asc':
                $qb->orderBy('f.goldChange', 'ASC');
                break;
            case 'date-desc':
            default:
                $qb->orderBy('f.createdAt', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }
}
