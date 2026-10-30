<?php

declare(strict_types=1);

namespace App\Repository\Team;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\FinancialRecord;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordType;
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
    public function findByTeamFiltered(Team $team, ?string $type = null, ?string $actor = null, ?string $sort = null, ?int $page = null, ?int $limit = null): array
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
                $qb->orderBy('f.createdAt', \SortDirection::Ascending);
                break;
            case 'amount-desc':
                $qb->orderBy('f.goldChange', \SortDirection::Descending);
                break;
            case 'amount-asc':
                $qb->orderBy('f.goldChange', \SortDirection::Ascending);
                break;
            case 'date-desc':
            default:
                $qb->orderBy('f.createdAt', \SortDirection::Descending);
                break;
        }

        if (null !== $page && null !== $limit) {
            $qb->setFirstResult(($page - 1) * $limit)
               ->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByTeamFiltered(Team $team, ?string $type = null, ?string $actor = null): int
    {
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
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

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<FinancialRecord>
     */
    public function findRecentByTeamAndType(Team $team, string $type, int $limit = 8): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.team = :team')
            ->andWhere('f.type = :type')
            ->setParameter('team', $team)
            ->setParameter('type', $type)
            ->orderBy('f.createdAt', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<FinancialRecord>
     */
    public function findRecentByTeam(Team $team, int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.team = :team')
            ->setParameter('team', $team)
            ->orderBy('f.createdAt', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{income: int, expense: int, net: int, transaction_count: int}
     */
    public function getGoldSummarySince(Team $team, ?\DateTimeImmutable $since = null): array
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

    public function hasArenaRevenueForFixture(Team $team, int $fixtureId): bool
    {
        /** @var list<FinancialRecord> $records */
        $records = $this->createQueryBuilder('f')
            ->where('f.team = :team')
            ->andWhere('f.type = :type')
            ->setParameter('team', $team)
            ->setParameter('type', FinancialRecordType::ArenaRevenue)
            ->getQuery()
            ->getResult();

        foreach ($records as $record) {
            if ((int) ($record->getContext()['fixture_id'] ?? 0) === $fixtureId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function findPaidArenaRevenueFixtureIds(Kingdom $kingdom): array
    {
        /** @var list<FinancialRecord> $records */
        $records = $this->createQueryBuilder('f')
            ->innerJoin('f.team', 't')
            ->where('t.kingdom = :kingdom')
            ->andWhere('f.type = :type')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('type', FinancialRecordType::ArenaRevenue)
            ->getQuery()
            ->getResult();

        $ids = [];
        foreach ($records as $record) {
            $fixtureId = (int) ($record->getContext()['fixture_id'] ?? 0);
            if ($fixtureId > 0) {
                $ids[$fixtureId] = $fixtureId;
            }
        }

        return array_values($ids);
    }
}
