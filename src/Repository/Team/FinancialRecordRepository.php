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

        if (null !== $type && '' !== $type) {
            $qb->andWhere('f.type = :type')
               ->setParameter('type', $type);
        }

        if (null !== $actor && '' !== $actor) {
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

    /**
     * @return list<FinancialRecord>
     */
    public function findRecentByTeamAndType(\App\Entity\Team\Team $team, string $type, int $limit = 8): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.team = :team')
            ->andWhere('f.type = :type')
            ->setParameter('team', $team)
            ->setParameter('type', $type)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<FinancialRecord>
     */
    public function findRecentByTeam(\App\Entity\Team\Team $team, int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.team = :team')
            ->setParameter('team', $team)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{income: int, expense: int, net: int, transaction_count: int}
     */
    public function getGoldSummarySince(\App\Entity\Team\Team $team, ?\DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('SUM(CASE WHEN f.goldChange > 0 THEN f.goldChange ELSE 0 END) AS income')
            ->addSelect('SUM(CASE WHEN f.goldChange < 0 THEN f.goldChange ELSE 0 END) AS expense_raw')
            ->addSelect('COUNT(f.id) AS transaction_count')
            ->where('f.team = :team')
            ->setParameter('team', $team);

        if (null !== $since) {
            $qb->andWhere('f.createdAt >= :since')
                ->setParameter('since', $since);
        }

        /** @var array{income: numeric-string|null, expense_raw: numeric-string|null, transaction_count: numeric-string|null} $result */
        $result = $qb->getQuery()->getSingleResult();

        $income = (int) ($result['income'] ?? 0);
        $expenseRaw = (int) ($result['expense_raw'] ?? 0);

        return [
            'income' => $income,
            'expense' => abs($expenseRaw),
            'net' => $income + $expenseRaw,
            'transaction_count' => (int) ($result['transaction_count'] ?? 0),
        ];
    }
}
