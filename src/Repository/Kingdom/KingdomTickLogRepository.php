<?php

declare(strict_types=1);

namespace App\Repository\Kingdom;

use App\Entity\FriendlyMatch\FriendlyMatch;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickEvent;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KingdomTickLog>
 */
class KingdomTickLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KingdomTickLog::class);
    }

    /** @param array<string, KingdomTickEvent> $events */
    public function attachToEvent(KingdomTickLog $log, array &$events): void
    {
        $time = $log->getScheduledAt()->setTimezone(new \DateTimeZone('UTC'));
        $key = $log->getTickType()->value.'|'.$time->format('Y-m-d H:i:s');
        if (!isset($events[$key])) {
            $em = $this->getEntityManager();
            $event = $em->getRepository(KingdomTickEvent::class)->findOneBy([
                'kingdom' => $log->getKingdom(),
                'tickType' => $log->getTickType(),
                'scheduledAt' => $time,
            ]);
            if (null === $event) {
                $event = new KingdomTickEvent($log->getKingdom(), $log->getTickType(), $time);
                $em->persist($event);
            }
            $events[$key] = $event;
        }
        $log->setEvent($events[$key]);
    }

    /**
     * @param array<string, mixed>                          $criteria
     * @param array<string, 'ASC'|'DESC'|'asc'|'desc'>|null $orderBy
     *
     * @return list<KingdomTickLog>
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $query = $this->queryForCriteria($criteria);
        foreach ($orderBy ?? [] as $field => $direction) {
            $alias = in_array($field, ['kingdom', 'tickType', 'scheduledAt'], true) ? 'event' : 'l';
            $query->addOrderBy($alias.'.'.$field, 'DESC' === strtoupper($direction) ? 'DESC' : 'ASC');
        }
        if (null !== $limit) {
            $query->setMaxResults($limit);
        }
        if (null !== $offset) {
            $query->setFirstResult($offset);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * @param array<string, mixed>                          $criteria
     * @param array<string, 'ASC'|'DESC'|'asc'|'desc'>|null $orderBy
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?KingdomTickLog
    {
        return $this->findBy($criteria, $orderBy, 1)[0] ?? null;
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return int<0, max>
     */
    public function count(array $criteria = []): int
    {
        return max(0, (int) $this->queryForCriteria($criteria)->select('COUNT(l.id)')->getQuery()->getSingleScalarResult());
    }

    /** @param array<string, mixed> $criteria */
    private function queryForCriteria(array $criteria): QueryBuilder
    {
        $query = $this->createQueryBuilder('l')->innerJoin('l.event', 'event');
        $index = 0;
        foreach ($criteria as $field => $value) {
            $alias = in_array($field, ['kingdom', 'tickType', 'scheduledAt'], true) ? 'event' : 'l';
            $column = $alias.'.'.$field;
            $parameter = 'criterion'.++$index;
            if (null === $value) {
                $query->andWhere($column.' IS NULL');
            } elseif (is_array($value)) {
                if ([] === $value) {
                    $query->andWhere('1 = 0');
                } else {
                    $query->andWhere($column.' IN (:'.$parameter.')')->setParameter($parameter, $value);
                }
            } else {
                $query->andWhere($column.' = :'.$parameter)->setParameter($parameter, $value);
            }
        }

        return $query;
    }

    /**
     * Gets the latest completed scheduledAt datetime for a specific tick type, optionally scoped by team or fixture.
     */
    public function getLatestCompletedTickTime(
        Kingdom $kingdom,
        TickType $tickType,
        ?\App\Entity\Team\Team $team = null,
        ?\App\Entity\League\LeagueFixture $fixture = null,
        ?FriendlyMatch $friendlyMatch = null,
    ): ?\DateTimeImmutable {
        $qb = $this->createQueryBuilder('l')
            ->innerJoin('l.event', 'event')
            ->where('event.kingdom = :kingdom')
            ->andWhere('event.tickType = :tickType')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('tickType', $tickType)
            ->setParameter('status', 'completed');

        if (null !== $team) {
            $qb->andWhere('l.team = :team')
               ->setParameter('team', $team);
        } else {
            $qb->andWhere('l.team IS NULL');
        }

        if (null !== $fixture) {
            $qb->andWhere('l.fixture = :fixture')
               ->setParameter('fixture', $fixture);
        } else {
            $qb->andWhere('l.fixture IS NULL');
        }

        if (null !== $friendlyMatch) {
            $qb->andWhere('l.friendlyMatch = :friendlyMatch')
               ->setParameter('friendlyMatch', $friendlyMatch);
        } else {
            $qb->andWhere('l.friendlyMatch IS NULL');
        }

        /** @var KingdomTickLog|null $latest */
        $latest = $qb->orderBy('event.scheduledAt', \SortDirection::Descending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $latest?->getScheduledAt();
    }

    public function hasFailedTicks(Kingdom $kingdom): bool
    {
        $count = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->innerJoin('l.event', 'event')
            ->where('event.kingdom = :kingdom')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('status', 'failed')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function hasProcessingTicks(Kingdom $kingdom): bool
    {
        $count = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->innerJoin('l.event', 'event')
            ->where('event.kingdom = :kingdom')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('statuses', ['dispatched', 'processing'])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function findOldestAwaitingCombat(Kingdom $kingdom): ?KingdomTickLog
    {
        return $this->findOneBy(
            ['kingdom' => $kingdom, 'status' => 'awaiting_combat'],
            ['scheduledAt' => 'ASC', 'id' => 'ASC'],
        );
    }

    /**
     * @return list<KingdomTickLog>
     */
    public function findAwaitingCombatAt(Kingdom $kingdom, \DateTimeImmutable $time): array
    {
        return $this->findBy(['kingdom' => $kingdom, 'status' => 'awaiting_combat', 'scheduledAt' => $time]);
    }

    public function getOldestPendingTime(Kingdom $kingdom): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('l')
            ->select('MIN(event.scheduledAt)')
            ->innerJoin('l.event', 'event')
            ->where('event.kingdom = :kingdom')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        if (null === $result) {
            return null;
        }

        return new \DateTimeImmutable((string) $result, new \DateTimeZone('UTC'));
    }

    public function getHighestPendingPriorityAt(Kingdom $kingdom, \DateTimeImmutable $time): ?int
    {
        /** @var list<KingdomTickLog> $logs */
        $logs = $this->createQueryBuilder('l')
            ->innerJoin('l.event', 'event')
            ->where('event.kingdom = :kingdom')
            ->andWhere('event.scheduledAt = :time')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('time', $time)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getResult();

        if (empty($logs)) {
            return null;
        }

        $minPriority = null;
        foreach ($logs as $log) {
            $priority = $log->getTickType()->getPriority();
            if (null === $minPriority || $priority < $minPriority) {
                $minPriority = $priority;
            }
        }

        return $minPriority;
    }

    /**
     * @return list<KingdomTickLog>
     */
    public function findPendingTicksInGroup(Kingdom $kingdom, \DateTimeImmutable $time, int $priority): array
    {
        /** @var list<KingdomTickLog> $logs */
        $logs = $this->createQueryBuilder('l')
            ->innerJoin('l.event', 'event')
            ->where('event.kingdom = :kingdom')
            ->andWhere('event.scheduledAt = :time')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('time', $time)
            ->setParameter('status', 'pending')
            ->orderBy('l.id', \SortDirection::Ascending)
            ->getQuery()
            ->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)
            ->getResult();

        return array_values(array_filter($logs, static function (KingdomTickLog $log) use ($priority): bool {
            return $log->getTickType()->getPriority() === $priority;
        }));
    }

    public function recoverStaleTicks(Kingdom $kingdom, \DateTimeImmutable $threshold): int
    {
        if (null === $kingdom->getId()) {
            return 0;
        }

        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            "UPDATE kingdom_tick_log SET status = CASE WHEN retry_count >= 2 THEN 'failed' ELSE 'pending' END, "
            ."error_message = CASE WHEN retry_count >= 2 THEN 'Dispatch timeout. Maximum retries reached (3).' ELSE 'Dispatch timeout. Automatically rescheduled.' END, "
            .'executed_at = ?, retry_count = retry_count + 1 '
            ."WHERE event_id IN (SELECT id FROM kingdom_tick_event WHERE kingdom_id = ?) AND status = 'dispatched' AND executed_at < ?",
            [gmdate('Y-m-d H:i:s'), $kingdom->getId(), $threshold->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
        );
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(Kingdom $kingdom): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.status AS status, COUNT(l.id) AS cnt')
            ->innerJoin('l.event', 'event')
            ->where('event.kingdom = :kingdom')
            ->setParameter('kingdom', $kingdom)
            ->groupBy('l.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [
            'pending' => 0,
            'dispatched' => 0,
            'processing' => 0,
            'awaiting_combat' => 0,
            'completed' => 0,
            'failed' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $counts[$status] = (int) $row['cnt'];
        }

        return $counts;
    }

    public function findLatestCompleted(Kingdom $kingdom): ?KingdomTickLog
    {
        /** @var KingdomTickLog|null $log */
        $log = $this->createQueryBuilder('l')
            ->innerJoin('l.event', 'event')
            ->where('event.kingdom = :kingdom')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('status', 'completed')
            ->orderBy('event.scheduledAt', \SortDirection::Descending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $log;
    }

    /**
     * @return list<KingdomTickLog>
     */
    public function findFailed(Kingdom $kingdom, ?int $tickId = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->innerJoin('l.event', 'event')
            ->where('event.kingdom = :kingdom')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('status', 'failed')
            ->orderBy('event.scheduledAt', \SortDirection::Ascending)
            ->addOrderBy('l.id', \SortDirection::Ascending);

        if (null !== $tickId) {
            $qb->andWhere('l.id = :tickId')
                ->setParameter('tickId', $tickId);
        }

        /** @var list<KingdomTickLog> $logs */
        $logs = $qb->getQuery()->getResult();

        return $logs;
    }
}
